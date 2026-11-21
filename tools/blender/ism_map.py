"""ISM indoor map pipeline for Blender (4.2 or newer).

Turns iPhone LiDAR scans of a mall into the map package that the
MapsNavigation module imports: per-floor plan images with their pixel-to-metre
transform, a light 3D model per floor, and the navigation graph with QR
checkpoints, shop entrances, facilities and vertical connectors.

Three ways to use it:

* Command line (no window)::

      blender -b --factory-startup -P tools/blender/ism_map.py -- <command> [options]

  Commands: ``make-test-scan``, ``prepare``, ``draft-graph``, ``validate``,
  ``export``. Add ``--help`` after a command for its options.

* Inside Blender: open this file in the Text Editor and press Run Script. An
  "ISM Map" tab appears in the 3D View sidebar (press N).

* As an add-on: Edit > Preferences > Add-ons > Install from Disk.

Coordinate frame (``ism-mall-local-v1``): metres, right-handed, x and y
horizontal, z up. A yaw is measured in degrees counterclockwise from +x about
z. The exported glTF files are Y-up as glTF requires:
``(gltf_x, gltf_y, gltf_z) = (x, z, -y)``.

See tools/blender/README.md for the full workflow.
"""

bl_info = {
    "name": "ISM Map Pipeline",
    "author": "Integrated Smart Mall team",
    "version": (1, 0, 0),
    "blender": (4, 2, 0),
    "location": "3D View > Sidebar > ISM Map",
    "description": "Prepare LiDAR mall scans, draft navigation graphs, place QR checkpoints and export the map package",
    "category": "Import-Export",
}

import argparse
import datetime
import json
import math
import os
import re
import sys
from collections import Counter, defaultdict, deque

import bmesh
import bpy
import numpy as np
from mathutils import Matrix, Vector
from mathutils.bvhtree import BVHTree
from mathutils.kdtree import KDTree

FORMAT = "ism-map-package"
FORMAT_VERSION = 1
COORDINATE_FRAME = (
    "ism-mall-local-v1: metres; right-handed; x and y horizontal, z up; "
    "yaw in degrees counterclockwise from +x about z, 0 to 360"
)
GLB_AXES = "glTF Y-up: (gltf_x, gltf_y, gltf_z) = (x, z, -y)"
PLAN_TRANSFORM = (
    "pixel (0,0) is the top-left corner of the image; "
    "x = world_origin_x_m + (px - pixel_origin_x) * metres_per_pixel; "
    "y = world_origin_y_m - (py - pixel_origin_y) * metres_per_pixel"
)

FLOOR_PREFIX = "ISM Floor "
MARKER_KINDS = ("checkpoint", "shop_entrance", "connector_stop", "facility")
CONNECTOR_TYPES = ("elevator", "escalator", "stairs")
DIRECTIONS = ("both", "up", "down")
FACILITY_TYPES = ("toilet", "prayer_room", "information", "exit", "atm", "lift", "other")
CODE_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$")
PLACEHOLDER = "CHANGE-ME"

SETTINGS = {
    "ism_walk_mps": 1.2,
    "ism_elevator_wait_s": 30.0,
    "ism_elevator_mps": 1.0,
    "ism_escalator_mps": 0.5,
    "ism_stairs_vertical_mps": 0.4,
    "ism_link_max_m": 6.0,
    "ism_checkpoint_standoff_m": 0.8,
}

FLOOR_BAND_M = 0.15
OBSTACLE_TOP_M = 2.0
SNAP_M = 0.3
MERGE_M = 0.05


class PipelineError(Exception):
    """A problem the user must fix; the message says how."""


# ---------------------------------------------------------------------------
# Small helpers
# ---------------------------------------------------------------------------


def log(message):
    """Print a progress line that stands out in Blender's console output."""
    print(f"[ism-map] {message}", flush=True)


def setting(name):
    """Read a pipeline setting from the scene, falling back to the default."""
    scene = bpy.context.scene
    return float(scene.get(name, SETTINGS[name]))


def ensure_settings():
    """Store the default settings on the scene so they are visible and editable."""
    scene = bpy.context.scene
    for key, value in SETTINGS.items():
        if key not in scene:
            scene[key] = value
    scene.unit_settings.system = "METRIC"
    scene.unit_settings.scale_length = 1.0


def slug(text, limit=24):
    """Make a short code-safe token from free text."""
    token = re.sub(r"[^A-Za-z0-9]+", "-", str(text)).strip("-")
    return (token or "X")[:limit].strip("-")


def yaw_deg(direction):
    """Heading of a horizontal vector in degrees, counterclockwise from +x, 0 to 360."""
    return math.degrees(math.atan2(direction[1], direction[0])) % 360.0


def polyline_length(points):
    """Length of a 2D polyline."""
    if len(points) < 2:
        return 0.0
    array = np.asarray(points, dtype=np.float64)
    return float(np.linalg.norm(np.diff(array, axis=0), axis=1).sum())


def select_only(obj):
    """Make obj the only selected object and the active one."""
    view_layer = bpy.context.view_layer
    for other in list(view_layer.objects):
        if other is not None and other.select_get():
            other.select_set(False)
    obj.select_set(True)
    view_layer.objects.active = obj


def link(obj, collection):
    """Link an object to exactly one collection."""
    for other in list(obj.users_collection):
        other.objects.unlink(obj)
    collection.objects.link(obj)


def union_find(count, left, right):
    """Connected components by hooking and pointer jumping, fully vectorised.

    Returns an array where equal values mean the same component.
    """
    parent = np.arange(count, dtype=np.int64)
    if count == 0 or len(left) == 0:
        return parent
    left = np.asarray(left, dtype=np.int64)
    right = np.asarray(right, dtype=np.int64)
    while True:
        root_left = parent[left]
        root_right = parent[right]
        differ = root_left != root_right
        if not differ.any():
            return parent
        low = np.minimum(root_left[differ], root_right[differ])
        high = np.maximum(root_left[differ], root_right[differ])
        np.minimum.at(parent, high, low)
        while True:
            jumped = parent[parent]
            if np.array_equal(jumped, parent):
                break
            parent = jumped


# ---------------------------------------------------------------------------
# Floors and objects in the .blend
# ---------------------------------------------------------------------------


def floor_collections():
    """Map floor code to its collection."""
    return {c["ism_floor_code"]: c for c in bpy.data.collections if "ism_floor_code" in c}


def ensure_floor(code, elevation, name=None):
    """Create or update the collection that holds one floor's objects."""
    if not CODE_PATTERN.match(code) or len(code) > 20:
        raise PipelineError(f"Floor code '{code}' must be 1-20 letters, digits, dot, dash or underscore (it must match floors.code in the app).")
    collection = floor_collections().get(code)
    if collection is None:
        collection = bpy.data.collections.new(FLOOR_PREFIX + code)
        bpy.context.scene.collection.children.link(collection)
    collection["ism_floor_code"] = code
    collection["ism_elevation_m"] = float(elevation)
    collection["ism_floor_name"] = name or collection.get("ism_floor_name", f"Floor {code}")
    return collection


def object_with_role(collection, role):
    """The first object in a floor collection carrying ``ism_role``."""
    for obj in collection.all_objects:
        if obj.get("ism_role") == role:
            return obj
    return None


def floors():
    """All floors sorted by elevation, with their scan and graph objects."""
    result = []
    for code, collection in floor_collections().items():
        result.append(
            {
                "code": code,
                "name": str(collection.get("ism_floor_name", f"Floor {code}")),
                "elevation": float(collection["ism_elevation_m"]),
                "collection": collection,
                "scan": object_with_role(collection, "scan"),
                "graph": object_with_role(collection, "graph"),
            }
        )
    result.sort(key=lambda f: (f["elevation"], f["code"]))
    return result


def floor_for_height(z, floor_list=None):
    """The floor whose level a point belongs to: the highest floor at or below z + 0.5 m."""
    floor_list = floor_list or floors()
    if not floor_list:
        raise PipelineError("There are no floors yet. Import a scan first (prepare).")
    candidates = [f for f in floor_list if f["elevation"] <= z + 0.5]
    return candidates[-1] if candidates else floor_list[0]


# ---------------------------------------------------------------------------
# Mesh arrays
# ---------------------------------------------------------------------------


def vertex_array(mesh, matrix=None):
    """Vertex positions as (n, 3) float64, optionally transformed."""
    co = np.empty(len(mesh.vertices) * 3, dtype=np.float64)
    mesh.vertices.foreach_get("co", co)
    co = co.reshape(-1, 3)
    if matrix is not None:
        m = np.array(matrix, dtype=np.float64)
        co = co @ m[:3, :3].T + m[:3, 3]
    return co


def polygon_arrays(mesh):
    """Face normals, centres, areas and first vertex index."""
    count = len(mesh.polygons)
    normals = np.empty(count * 3, dtype=np.float64)
    centres = np.empty(count * 3, dtype=np.float64)
    areas = np.empty(count, dtype=np.float64)
    loop_start = np.empty(count, dtype=np.int64)
    mesh.polygons.foreach_get("normal", normals)
    mesh.polygons.foreach_get("center", centres)
    mesh.polygons.foreach_get("area", areas)
    mesh.polygons.foreach_get("loop_start", loop_start)
    loop_vertex = np.empty(len(mesh.loops), dtype=np.int64)
    mesh.loops.foreach_get("vertex_index", loop_vertex)
    first_vertex = loop_vertex[loop_start] if count else np.empty(0, dtype=np.int64)
    return normals.reshape(-1, 3), centres.reshape(-1, 3), areas, first_vertex


def triangle_count(mesh):
    """Triangles after triangulation."""
    totals = np.empty(len(mesh.polygons), dtype=np.int64)
    mesh.polygons.foreach_get("loop_total", totals)
    return int(np.maximum(totals - 2, 0).sum())


def delete_faces(obj, mask):
    """Delete faces where mask is True, then any vertices left without faces."""
    indices = np.flatnonzero(mask)
    if len(indices) == 0:
        return 0
    bm = bmesh.new()
    bm.from_mesh(obj.data)
    bm.faces.ensure_lookup_table()
    bmesh.ops.delete(bm, geom=[bm.faces[int(i)] for i in indices], context="FACES")
    loose = [v for v in bm.verts if not v.link_faces]
    if loose:
        bmesh.ops.delete(bm, geom=loose, context="VERTS")
    bm.to_mesh(obj.data)
    bm.free()
    obj.data.update()
    return len(indices)


def clean_mesh(obj, merge_distance=0.002):
    """Merge duplicate vertices and drop geometry that has no faces."""
    bm = bmesh.new()
    bm.from_mesh(obj.data)
    bmesh.ops.remove_doubles(bm, verts=list(bm.verts), dist=merge_distance)
    loose = [v for v in bm.verts if not v.link_faces]
    if loose:
        bmesh.ops.delete(bm, geom=loose, context="VERTS")
    bm.to_mesh(obj.data)
    bm.free()
    obj.data.update()


def replace_with_evaluated(obj):
    """Bake the object's modifiers into its mesh without using operators."""
    depsgraph = bpy.context.evaluated_depsgraph_get()
    evaluated = obj.evaluated_get(depsgraph)
    baked = bpy.data.meshes.new_from_object(evaluated, preserve_all_data_layers=True, depsgraph=depsgraph)
    old = obj.data
    obj.modifiers.clear()
    obj.data = baked
    if old.users == 0:
        bpy.data.meshes.remove(old)


def decimate_to(obj, max_triangles):
    """Reduce the mesh to about max_triangles triangles. Returns the new count."""
    count = triangle_count(obj.data)
    if max_triangles <= 0 or count <= max_triangles:
        return count
    modifier = obj.modifiers.new("ism_decimate", "DECIMATE")
    modifier.decimate_type = "COLLAPSE"
    modifier.ratio = max(0.001, max_triangles / count)
    modifier.use_collapse_triangulate = True
    replace_with_evaluated(obj)
    return triangle_count(obj.data)


# ---------------------------------------------------------------------------
# prepare: import and clean one floor scan
# ---------------------------------------------------------------------------

IMPORTERS = {
    ".glb": lambda p: bpy.ops.import_scene.gltf(filepath=p),
    ".gltf": lambda p: bpy.ops.import_scene.gltf(filepath=p),
    ".obj": lambda p: bpy.ops.wm.obj_import(filepath=p),
    ".ply": lambda p: bpy.ops.wm.ply_import(filepath=p),
    ".stl": lambda p: bpy.ops.wm.stl_import(filepath=p),
    ".usd": lambda p: bpy.ops.wm.usd_import(filepath=p),
    ".usda": lambda p: bpy.ops.wm.usd_import(filepath=p),
    ".usdc": lambda p: bpy.ops.wm.usd_import(filepath=p),
    ".usdz": lambda p: bpy.ops.wm.usd_import(filepath=p),
    ".fbx": lambda p: bpy.ops.import_scene.fbx(filepath=p),
}


def import_scan_objects(path):
    """Import a scan file and return one joined mesh object in world coordinates."""
    extension = os.path.splitext(path)[1].lower()
    if extension not in IMPORTERS:
        raise PipelineError(f"Unsupported scan format '{extension}'. Export GLB, OBJ, USDZ, PLY (mesh), STL or FBX from the scanning app.")
    if not os.path.isfile(path):
        raise PipelineError(f"Scan file not found: {path}")
    before = set(bpy.data.objects)
    IMPORTERS[extension](path)
    created = [o for o in bpy.data.objects if o not in before]
    meshes = [o for o in created if o.type == "MESH" and len(o.data.polygons) > 0]
    if not meshes:
        for o in created:
            bpy.data.objects.remove(o, do_unlink=True)
        raise PipelineError("The scan has no faces. Export a mesh, not a point cloud, from the scanning app.")
    for obj in meshes:
        world = obj.matrix_world.copy()
        if obj.data.users > 1:
            obj.data = obj.data.copy()
        obj.parent = None
        obj.data.transform(world)
        obj.matrix_world = Matrix.Identity(4)
    for o in created:
        if o not in meshes:
            bpy.data.objects.remove(o, do_unlink=True)
    target = meshes[0]
    if len(meshes) > 1:
        with bpy.context.temp_override(
            active_object=target,
            object=target,
            selected_objects=meshes,
            selected_editable_objects=meshes,
        ):
            bpy.ops.object.join()
    return target


def remove_fragments(obj, min_diagonal):
    """Delete floating pieces whose bounding box diagonal is under min_diagonal metres."""
    mesh = obj.data
    edges = np.empty(len(mesh.edges) * 2, dtype=np.int64)
    mesh.edges.foreach_get("vertices", edges)
    edges = edges.reshape(-1, 2)
    co = vertex_array(mesh)
    labels = union_find(len(co), edges[:, 0], edges[:, 1])
    low = np.full_like(co, np.inf)
    high = np.full_like(co, -np.inf)
    np.minimum.at(low, labels, co)
    np.maximum.at(high, labels, co)
    diagonal = np.linalg.norm(high - low, axis=1)
    small_component = diagonal < min_diagonal
    _, _, _, first_vertex = polygon_arrays(mesh)
    face_mask = small_component[labels[first_vertex]]
    pieces = len(np.unique(labels[first_vertex][face_mask])) if face_mask.any() else 0
    delete_faces(obj, face_mask)
    return pieces


def detect_floor_height(mesh):
    """Height of the largest up-facing area: the walking floor of the scan."""
    normals, centres, areas, _ = polygon_arrays(mesh)
    up = normals[:, 2] > 0.85
    if not up.any():
        raise PipelineError("No floor found in the scan (no up-facing surfaces). Was the floor scanned?")
    z = centres[up, 2]
    weight = areas[up]
    bins = np.arange(z.min() - 0.02, z.max() + 0.04, 0.02)
    histogram, edges = np.histogram(z, bins=bins, weights=weight)
    peak = int(np.argmax(histogram))
    centre = 0.5 * (edges[peak] + edges[peak + 1])
    near = np.abs(z - centre) < 0.03
    return float(np.average(z[near], weights=weight[near]))


def detect_wall_yaw(mesh):
    """Dominant wall direction modulo 90 degrees and how strong it is (0 to 1)."""
    normals, _, areas, _ = polygon_arrays(mesh)
    horizontal = np.hypot(normals[:, 0], normals[:, 1])
    wall = (np.abs(normals[:, 2]) < 0.3) & (horizontal > 0.5)
    if not wall.any():
        return 0.0, 0.0
    angle = np.arctan2(normals[wall, 1], normals[wall, 0])
    weight = areas[wall]
    s = float(np.sum(weight * np.sin(4 * angle)))
    c = float(np.sum(weight * np.cos(4 * angle)))
    strength = math.hypot(s, c) / float(weight.sum())
    return math.degrees(math.atan2(s, c) / 4.0), strength


def prepare_scan(
    path,
    floor_code,
    elevation,
    floor_name=None,
    scale=1.0,
    yaw=None,
    yaw_offset=0.0,
    keep_xy=False,
    offset=(0.0, 0.0),
    ceiling_cut=2.6,
    min_fragment=0.5,
    max_triangles=400_000,
    replace=False,
):
    """Import one floor scan, clean it, level it, square it to the axes and file it under its floor.

    Returns the metadata stored on the scan object.
    """
    ensure_settings()
    existing = floor_collections().get(floor_code)
    if existing is not None and object_with_role(existing, "scan") is not None:
        if not replace:
            raise PipelineError(f"Floor {floor_code} already has a scan. Use --replace to swap it.")
        old = object_with_role(existing, "scan")
        bpy.data.objects.remove(old, do_unlink=True)

    log(f"importing {path}")
    obj = import_scan_objects(path)
    mesh = obj.data
    source_triangles = triangle_count(mesh)

    if scale != 1.0:
        mesh.transform(Matrix.Scale(scale, 4))

    clean_mesh(obj)
    fragments = remove_fragments(obj, min_fragment)
    log(f"removed {fragments} floating fragments smaller than {min_fragment} m")

    floor_z = detect_floor_height(mesh)
    detected_yaw, strength = detect_wall_yaw(mesh)
    rotation = float(yaw) if yaw is not None else -detected_yaw + float(yaw_offset)
    mesh.transform(Matrix.Rotation(math.radians(rotation), 4, "Z"))
    log(f"floor detected at z={floor_z:.3f} m; rotating {rotation:.2f} deg (wall alignment strength {strength:.2f})")

    _, centres, _, _ = polygon_arrays(mesh)
    ceiling = centres[:, 2] > floor_z + ceiling_cut
    below = centres[:, 2] < floor_z - 0.5
    removed_ceiling = delete_faces(obj, ceiling | below)

    co = vertex_array(mesh)
    shift_x = float(offset[0]) - (0.0 if keep_xy else float(co[:, 0].min()))
    shift_y = float(offset[1]) - (0.0 if keep_xy else float(co[:, 1].min()))
    shift_z = float(elevation) - floor_z
    mesh.transform(Matrix.Translation((shift_x, shift_y, shift_z)))

    triangles = decimate_to(obj, max_triangles)

    collection = ensure_floor(floor_code, elevation, floor_name)
    link(obj, collection)
    obj.name = f"Scan {floor_code}"
    obj.data.name = f"Scan {floor_code}"
    obj["ism_role"] = "scan"
    obj["ism_floor"] = floor_code
    metadata = {
        "source_file": os.path.basename(path),
        "source_triangles": source_triangles,
        "scale": scale,
        "rotation_deg": round(rotation, 4),
        "detected_wall_yaw_deg": round(detected_yaw, 4),
        "wall_alignment_strength": round(strength, 4),
        "translation_m": [round(shift_x, 4), round(shift_y, 4), round(shift_z, 4)],
        "detected_floor_z_m": round(floor_z, 4),
        "fragments_removed": fragments,
        "faces_removed_above_ceiling_cut": removed_ceiling,
        "ceiling_cut_m": ceiling_cut,
        "triangles": triangles,
        "prepared_at": datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds"),
    }
    obj["ism_prepare"] = json.dumps(metadata)
    log(f"floor {floor_code}: {triangles} triangles at elevation {elevation} m")
    if strength < 0.3:
        log("warning: walls do not line up clearly; check the rotation in the top view and fix it with --yaw-deg")
    return metadata


# ---------------------------------------------------------------------------
# Rasterising the scan: floor and obstacle grids
# ---------------------------------------------------------------------------


def scan_bounds(floor_list, margin=1.0):
    """One rectangle covering every floor's scan, so all floor images line up."""
    lows, highs = [], []
    for f in floor_list:
        if f["scan"] is None:
            continue
        co = vertex_array(f["scan"].data, f["scan"].matrix_world)
        lows.append(co[:, :2].min(axis=0))
        highs.append(co[:, :2].max(axis=0))
    if not lows:
        raise PipelineError("No scans found. Import a scan for every floor first.")
    low = np.min(lows, axis=0) - margin
    high = np.max(highs, axis=0) + margin
    return float(low[0]), float(low[1]), float(high[0]), float(high[1])


def rasterise(obj, elevation, bounds, cell, seed=0, chunk=3_000_000):
    """Sample the scan surface and mark floor and obstacle cells.

    Floor cells hold an up-facing surface within 0.15 m of the floor level.
    Obstacle cells hold any surface between 0.15 m and 2.0 m above it.
    Row 0 is the north (max y) edge.
    """
    x0, y0, x1, y1 = bounds
    width = int(math.ceil((x1 - x0) / cell))
    height = int(math.ceil((y1 - y0) / cell))
    floor = np.zeros(height * width, dtype=bool)
    obstacle = np.zeros(height * width, dtype=bool)

    mesh = obj.data
    mesh.calc_loop_triangles()
    tri = np.empty(len(mesh.loop_triangles) * 3, dtype=np.int64)
    mesh.loop_triangles.foreach_get("vertices", tri)
    tri = tri.reshape(-1, 3)
    co = vertex_array(mesh, obj.matrix_world)
    a, b, c = co[tri[:, 0]], co[tri[:, 1]], co[tri[:, 2]]
    cross = np.cross(b - a, c - a)
    doubled = np.linalg.norm(cross, axis=1)
    normal_z = cross[:, 2] / np.maximum(doubled, 1e-12)
    counts = np.ceil(0.5 * doubled / (cell * cell) * 2.0).astype(np.int64)
    counts = np.clip(counts, 1, 5000)
    counts[doubled < 1e-12] = 0
    total = int(counts.sum())
    budget = 20_000_000
    if total > budget:
        counts = np.ceil(counts * (budget / total)).astype(np.int64)

    rng = np.random.default_rng(seed)
    triangle_index = np.repeat(np.arange(len(tri)), counts)
    for start in range(0, len(triangle_index), chunk):
        ti = triangle_index[start : start + chunk]
        r1 = np.sqrt(rng.random(len(ti)))
        r2 = rng.random(len(ti))
        p = (1 - r1)[:, None] * a[ti] + (r1 * (1 - r2))[:, None] * b[ti] + (r1 * r2)[:, None] * c[ti]
        col = np.floor((p[:, 0] - x0) / cell).astype(np.int64)
        row = np.floor((y1 - p[:, 1]) / cell).astype(np.int64)
        inside = (col >= 0) & (col < width) & (row >= 0) & (row < height)
        dz = p[:, 2] - elevation
        flat = row * width + col
        is_floor = inside & (normal_z[ti] > 0.8) & (np.abs(dz) < FLOOR_BAND_M)
        is_obstacle = inside & (dz > FLOOR_BAND_M) & (dz < OBSTACLE_TOP_M)
        floor[flat[is_floor]] = True
        obstacle[flat[is_obstacle]] = True
    return floor.reshape(height, width), obstacle.reshape(height, width)


def disk_offsets(radius):
    """Grid offsets inside a disk of the given radius in cells."""
    return [(dy, dx) for dy in range(-radius, radius + 1) for dx in range(-radius, radius + 1) if dy * dy + dx * dx <= radius * radius]


def dilate(mask, radius):
    """Binary dilation with a disk."""
    if radius <= 0:
        return mask.copy()
    height, width = mask.shape
    padded = np.pad(mask, radius)
    out = np.zeros_like(mask)
    for dy, dx in disk_offsets(radius):
        out |= padded[radius + dy : radius + dy + height, radius + dx : radius + dx + width]
    return out


def erode(mask, radius):
    """Binary erosion with a disk."""
    return ~dilate(~mask, radius)


def grid_labels(mask, diagonal=False):
    """Label connected True cells; -1 where mask is False."""
    height, width = mask.shape
    index = np.full(height * width, -1, dtype=np.int64)
    flat = np.flatnonzero(mask)
    index[flat] = np.arange(len(flat))
    index = index.reshape(height, width)
    left, right = [], []
    pairs = [((slice(None), slice(None, -1)), (slice(None), slice(1, None))), ((slice(None, -1), slice(None)), (slice(1, None), slice(None)))]
    if diagonal:
        pairs += [((slice(None, -1), slice(None, -1)), (slice(1, None), slice(1, None))), ((slice(None, -1), slice(1, None)), (slice(1, None), slice(None, -1)))]
    for first, second in pairs:
        both = mask[first] & mask[second]
        left.append(index[first][both])
        right.append(index[second][both])
    labels = union_find(len(flat), np.concatenate(left), np.concatenate(right))
    out = np.full(height * width, -1, dtype=np.int64)
    out[flat] = labels
    return out.reshape(height, width)


def fill_small_holes(mask, max_cells):
    """Fill enclosed gaps smaller than max_cells (scan noise), keep larger ones (pillars)."""
    holes = grid_labels(~mask)
    border = np.unique(np.concatenate([holes[0], holes[-1], holes[:, 0], holes[:, -1]]))
    ids, counts = np.unique(holes[holes >= 0], return_counts=True)
    small = ids[(counts < max_cells) & ~np.isin(ids, border)]
    out = mask.copy()
    out[np.isin(holes, small)] = True
    return out


def solid_obstacles(obstacle, cell, max_area_m2=2.0):
    """Fill small areas enclosed by obstacle outlines (pillars cut open at the ceiling cut)."""
    closed = erode(dilate(obstacle, 1), 1) | obstacle
    return fill_small_holes(closed, int(max_area_m2 / (cell * cell)))


def distance_to_edge(mask, cell, max_m=8.0):
    """Approximate distance from each True cell to the nearest False cell, in metres."""
    distance = np.zeros(mask.shape, dtype=np.float32)
    current = mask.copy()
    for _ in range(int(max_m / cell)):
        if not current.any():
            break
        distance[current] += cell
        current = erode(current, 1)
    return distance


def keep_large_regions(mask, min_cells):
    """Drop walkable islands smaller than min_cells."""
    labels = grid_labels(mask)
    ids, counts = np.unique(labels[labels >= 0], return_counts=True)
    keep = ids[counts >= min_cells]
    return np.isin(labels, keep) & mask


def thin(mask):
    """Zhang-Suen thinning to a one-cell-wide skeleton (vectorised)."""
    img = np.pad(mask.astype(bool), 1)
    changed = True
    while changed:
        changed = False
        for step in (0, 1):
            p2, p3, p4, p5 = img[:-2, 1:-1], img[:-2, 2:], img[1:-1, 2:], img[2:, 2:]
            p6, p7, p8, p9 = img[2:, 1:-1], img[2:, :-2], img[1:-1, :-2], img[:-2, :-2]
            ring = [p2, p3, p4, p5, p6, p7, p8, p9]
            neighbours = sum(r.astype(np.uint8) for r in ring)
            transitions = sum(((~ring[i]) & ring[(i + 1) % 8]).astype(np.uint8) for i in range(8))
            if step == 0:
                keep_shape = ~(p2 & p4 & p6) & ~(p4 & p6 & p8)
            else:
                keep_shape = ~(p2 & p4 & p8) & ~(p2 & p6 & p8)
            remove = img[1:-1, 1:-1] & (neighbours >= 2) & (neighbours <= 6) & (transitions == 1) & keep_shape
            if remove.any():
                img[1:-1, 1:-1] &= ~remove
                changed = True
    return img[1:-1, 1:-1]


# ---------------------------------------------------------------------------
# draft-graph: skeleton of the walkable area to an editable graph mesh
# ---------------------------------------------------------------------------

NEIGHBOURS_8 = [(-1, -1), (-1, 0), (-1, 1), (0, -1), (0, 1), (1, -1), (1, 0), (1, 1)]


def skeleton_graph(skeleton, to_world):
    """Trace a pixel skeleton into nodes and polyline edges."""
    rows, cols = np.nonzero(skeleton)
    pixels = set(zip(rows.tolist(), cols.tolist()))

    def neighbours(p):
        return [(p[0] + dy, p[1] + dx) for dy, dx in NEIGHBOURS_8 if (p[0] + dy, p[1] + dx) in pixels]

    node_pixels = {p for p in pixels if len(neighbours(p)) != 2}
    cluster = {}
    clusters = []

    def add_cluster(seed):
        members = [seed]
        cluster[seed] = len(clusters)
        queue = deque([seed])
        while queue:
            p = queue.popleft()
            for q in neighbours(p):
                if q in node_pixels and q not in cluster:
                    cluster[q] = len(clusters)
                    members.append(q)
                    queue.append(q)
        clusters.append(members)

    for p in sorted(node_pixels):
        if p not in cluster:
            add_cluster(p)

    edges = []
    direct = set()
    visited = set()
    covered = set(node_pixels)

    def trace_from(p):
        for q in neighbours(p):
            if q in node_pixels:
                pair = tuple(sorted((cluster[p], cluster[q])))
                if pair[0] != pair[1] and pair not in direct:
                    direct.add(pair)
                    edges.append((cluster[p], cluster[q], [p, q]))
                continue
            if (p, q) in visited:
                continue
            path = [p, q]
            previous, current = p, q
            while current not in node_pixels:
                options = [x for x in neighbours(current) if x != previous]
                if not options:
                    break
                step = next((x for x in options if x not in path[-4:]), options[0])
                path.append(step)
                previous, current = current, step
                if len(path) > len(pixels) + 2:
                    break
            visited.add((p, q))
            visited.add((path[-1], path[-2]))
            covered.update(path)
            if current in node_pixels:
                edges.append((cluster[p], cluster[current], path))

    for p in sorted(node_pixels):
        trace_from(p)
    remaining = sorted(pixels - covered)
    while remaining:
        seed = remaining[0]
        node_pixels.add(seed)
        add_cluster(seed)
        trace_from(seed)
        remaining = sorted(pixels - covered)

    positions = {i: np.mean([to_world(p) for p in members], axis=0) for i, members in enumerate(clusters)}
    polylines = []
    for a, b, path in edges:
        points = [positions[a]] + [to_world(p) for p in path[1:-1]] + [positions[b]]
        polylines.append([a, b, [np.asarray(x, dtype=np.float64) for x in points]])
    return positions, polylines


class DraftGraph:
    """Mutable graph used while cleaning a traced skeleton."""

    def __init__(self, positions, polylines):
        self.pos = {k: np.asarray(v, dtype=np.float64) for k, v in positions.items()}
        self.edges = {}
        self.incident = defaultdict(list)
        self.next_edge = 0
        for a, b, points in polylines:
            self.add(a, b, points)

    def add(self, a, b, points):
        eid = self.next_edge
        self.next_edge += 1
        self.edges[eid] = [a, b, list(points)]
        self.incident[a].append(eid)
        self.incident[b].append(eid)
        return eid

    def remove(self, eid):
        a, b, _ = self.edges.pop(eid)
        self.incident[a].remove(eid)
        self.incident[b].remove(eid)

    def degree(self, node):
        return len(self.incident[node])

    def length(self, eid):
        return polyline_length(self.edges[eid][2])

    def drop_isolated(self):
        for node in [n for n in self.pos if not self.incident[n]]:
            del self.pos[node]
            self.incident.pop(node, None)

    def prune_spurs(self, max_length, clearance_at=None, room_at=None, factor=2.5):
        """Remove dead-end branches that are short compared with the corridor they leave.

        A branch is noise when it is shorter than max_length, or shorter than
        factor times the distance to the nearest obstacle where it starts
        (skeleton branches into the corners of wide, shallow bays). A branch
        that narrows and then widens again passes a doorway into a room and is
        always kept.
        """

        def limit(a, b):
            if clearance_at is None:
                return max_length
            root = a if self.degree(a) > 1 else b
            return max(max_length, factor * clearance_at(self.pos[root]))

        def through_doorway(eid):
            if room_at is None:
                return False
            a, _, points = self.edges[eid]
            ordered = points if self.degree(a) > 1 else points[::-1]
            room = np.array([room_at(p) for p in ordered])
            narrowest = int(np.argmin(room))
            return float(room[narrowest:].max()) >= max(1.0, 1.6 * float(room[narrowest]))

        for _ in range(30):
            spurs = [
                e
                for e, (a, b, _) in self.edges.items()
                if a != b and (self.degree(a) == 1 or self.degree(b) == 1) and self.length(e) < limit(a, b) and not through_doorway(e)
            ]
            if not spurs:
                break
            for e in spurs:
                if e in self.edges:
                    self.remove(e)
            self.drop_isolated()

    def merge_degree_two(self):
        changed = True
        while changed:
            changed = False
            for node in list(self.pos):
                ids = self.incident.get(node, [])
                if len(ids) != 2 or ids[0] == ids[1]:
                    continue
                first, second = ids
                a1, b1, p1 = self.edges[first]
                a2, b2, p2 = self.edges[second]
                if b1 != node:
                    a1, b1, p1 = b1, a1, p1[::-1]
                if a2 != node:
                    a2, b2, p2 = b2, a2, p2[::-1]
                self.remove(first)
                self.remove(second)
                self.add(a1, b2, p1 + p2[1:])
                del self.pos[node]
                self.incident.pop(node, None)
                changed = True

    def collapse_short(self, min_length):
        changed = True
        while changed:
            changed = False
            for eid in list(self.edges):
                if eid not in self.edges:
                    continue
                a, b, points = self.edges[eid]
                if a == b or self.length(eid) >= min_length:
                    continue
                self.remove(eid)
                merged = 0.5 * (self.pos[a] + self.pos[b])
                for other in list(dict.fromkeys(self.incident[b])):
                    oa, ob, op = self.edges[other]
                    self.remove(other)
                    self.add(a if oa == b else oa, a if ob == b else ob, op)
                del self.pos[b]
                self.incident.pop(b, None)
                self.pos[a] = merged
                for other in list(dict.fromkeys(self.incident[a])):
                    if other not in self.edges:
                        continue
                    oa, ob, op = self.edges[other]
                    if oa == a:
                        op[0] = merged
                    if ob == a:
                        op[-1] = merged
                    if oa == ob and polyline_length(op) < 2 * min_length:
                        self.remove(other)
                changed = True

    def drop_short_loops(self, min_length):
        """Remove self-loops shorter than min_length (tracing artefacts)."""
        for eid in [e for e, (a, b, _) in self.edges.items() if a == b and self.length(e) < min_length]:
            self.remove(eid)
        self.drop_isolated()

    def drop_thin_cycles(self, ratio=0.15):
        """Remove out-and-back spikes: loops that enclose almost no area for their length.

        A self-loop, or the longer of two edges joining the same nodes, is
        dropped when enclosed area / perimeter is below ratio (metres). Real
        loops around benches or atrium islands enclose far more.
        """

        def thin_ring(points, perimeter):
            ring = np.asarray(points, dtype=np.float64)
            area = 0.5 * abs(np.dot(ring[:-1, 0], ring[1:, 1]) - np.dot(ring[1:, 0], ring[:-1, 1]))
            return perimeter > 0 and area / perimeter < ratio

        for eid in [e for e, (a, b, _) in self.edges.items() if a == b]:
            points = self.edges[eid][2]
            if thin_ring(points + [points[0]], self.length(eid)):
                self.remove(eid)
        groups = defaultdict(list)
        for eid, (a, b, _) in self.edges.items():
            if a != b:
                groups[(min(a, b), max(a, b))].append(eid)
        for (a, _), ids in groups.items():
            ids = sorted(ids, key=self.length)
            for longer in ids[1:]:
                if longer not in self.edges:
                    continue
                short_points = self.edges[ids[0]][2] if self.edges[ids[0]][0] == a else self.edges[ids[0]][2][::-1]
                long_points = self.edges[longer][2] if self.edges[longer][0] == a else self.edges[longer][2][::-1]
                ring = short_points + long_points[::-1]
                if thin_ring(ring, self.length(ids[0]) + self.length(longer)):
                    self.remove(longer)
        self.drop_isolated()

    def centre_junctions(self, best_point):
        """Move every junction (3+ edges) to the most open spot nearby, usually the corridor centre."""
        for node in list(self.pos):
            if self.degree(node) < 3:
                continue
            moved = np.asarray(best_point(self.pos[node]), dtype=np.float64)
            self.pos[node] = moved
            for eid in dict.fromkeys(self.incident[node]):
                a, b, points = self.edges[eid]
                if a == node:
                    points[0] = moved
                if b == node:
                    points[-1] = moved

    def trim_dead_ends(self, room_at, keep_ratio=0.75, max_cut=0.6):
        """End dead-end lines where the space is widest (shop centre, corridor end), not in a corner."""
        for eid in list(self.edges):
            a, b, points = self.edges[eid]
            if a == b or (self.degree(a) != 1 and self.degree(b) != 1) or (self.degree(a) == 1 and self.degree(b) == 1):
                continue
            free_at_start = self.degree(a) == 1
            ordered = points[::-1] if free_at_start else list(points)
            room = [room_at(p) for p in ordered]
            target = keep_ratio * max(room)
            cut = next((i for i in range(len(ordered) - 1, -1, -1) if room[i] >= target), len(ordered) - 1)
            kept = ordered[: cut + 1]
            total = polyline_length(ordered)
            if cut < 1 or polyline_length(kept) < max(1.0, (1 - max_cut) * total):
                continue
            free = a if free_at_start else b
            self.pos[free] = np.asarray(kept[-1], dtype=np.float64)
            self.edges[eid][2] = kept[::-1] if free_at_start else kept

    def drop_small_components(self, min_total_length):
        nodes = list(self.pos)
        index = {n: i for i, n in enumerate(nodes)}
        pairs = np.array([(index[a], index[b]) for a, b, _ in self.edges.values()], dtype=np.int64).reshape(-1, 2)
        labels = union_find(len(nodes), pairs[:, 0], pairs[:, 1])
        total = defaultdict(float)
        for eid, (a, _, _) in self.edges.items():
            total[labels[index[a]]] += self.length(eid)
        for eid in list(self.edges):
            a = self.edges[eid][0]
            if total[labels[index[a]]] < min_total_length:
                self.remove(eid)
        self.drop_isolated()


def simplify_polyline(points, tolerance):
    """Ramer-Douglas-Peucker; keeps the first and last point."""
    array = np.asarray(points, dtype=np.float64)
    if len(array) < 3:
        return [array[0], array[-1]]
    keep = np.zeros(len(array), dtype=bool)
    keep[0] = keep[-1] = True
    stack = [(0, len(array) - 1)]
    while stack:
        start, end = stack.pop()
        if end <= start + 1:
            continue
        segment = array[end] - array[start]
        length = np.linalg.norm(segment)
        middle = array[start + 1 : end]
        if length < 1e-9:
            distances = np.linalg.norm(middle - array[start], axis=1)
        else:
            distances = np.abs(segment[0] * (middle[:, 1] - array[start, 1]) - segment[1] * (middle[:, 0] - array[start, 0])) / length
        worst = int(np.argmax(distances))
        if distances[worst] > tolerance:
            split = start + 1 + worst
            keep[split] = True
            stack.append((start, split))
            stack.append((split, end))
    return [array[i] for i in np.flatnonzero(keep)]


def straighten(points, is_clear):
    """Greedy string pulling: skip points while the straight line stays walkable."""
    if len(points) < 3:
        return list(points)
    result = [points[0]]
    i = 0
    while i < len(points) - 1:
        j = i + 1
        while j + 1 < len(points) and is_clear(points[i], points[j + 1]):
            j += 1
        result.append(points[j])
        i = j
    return result


def walkable_area(scan, elevation, bounds, cell, clearance):
    """Cells where a shopper can stand with the given clearance from obstacles."""
    floor, obstacle = rasterise(scan, elevation, bounds, cell)
    floor = erode(dilate(floor, 2), 2)
    obstacle = solid_obstacles(obstacle, cell)
    radius = max(1, int(round(clearance / cell)))
    walkable = floor & ~dilate(obstacle, radius)
    walkable = fill_small_holes(walkable, int(0.5 / (cell * cell)))
    walkable = keep_large_regions(walkable, int(4.0 / (cell * cell)))
    return walkable


def draft_graph(floor_code, cell=0.1, clearance=0.35, spur=1.5, tolerance=0.2, replace=False):
    """Build a first navigation graph for a floor from its scan.

    The result is a mesh of vertices and edges named ``NavGraph <code>`` that
    you correct by hand in Edit Mode. Returns basic counts.
    """
    ensure_settings()
    floor = next((f for f in floors() if f["code"] == floor_code), None)
    if floor is None or floor["scan"] is None:
        raise PipelineError(f"Floor {floor_code} has no scan. Run prepare for it first.")
    if floor["graph"] is not None:
        if not replace:
            raise PipelineError(f"Floor {floor_code} already has a graph. Use --replace to draw a new draft (your edits are lost).")
        bpy.data.objects.remove(floor["graph"], do_unlink=True)

    elevation = floor["elevation"]
    co = vertex_array(floor["scan"].data, floor["scan"].matrix_world)
    bounds = (float(co[:, 0].min()) - 1, float(co[:, 1].min()) - 1, float(co[:, 0].max()) + 1, float(co[:, 1].max()) + 1)
    x0, _, _, y1 = bounds
    walkable = walkable_area(floor["scan"], elevation, bounds, cell, clearance)
    if not walkable.any():
        raise PipelineError(f"No walkable area found on floor {floor_code}. Check that its elevation matches the scan floor.")
    skeleton = thin(walkable)
    room = distance_to_edge(walkable, cell)

    def to_world(p):
        return (x0 + (p[1] + 0.5) * cell, y1 - (p[0] + 0.5) * cell)

    def cell_of(point):
        col = int(np.clip((point[0] - x0) / cell, 0, room.shape[1] - 1))
        row = int(np.clip((y1 - point[1]) / cell, 0, room.shape[0] - 1))
        return row, col

    def clearance_at(point):
        row, col = cell_of(point)
        return float(room[max(0, row - 2) : row + 3, max(0, col - 2) : col + 3].max())

    def room_at(point):
        row, col = cell_of(point)
        return float(room[row, col])

    def best_point(point, radius_m=1.2):
        row, col = cell_of(point)
        r = int(radius_m / cell)
        r0, c0 = max(0, row - r), max(0, col - r)
        window = room[r0 : row + r + 1, c0 : col + r + 1]
        rows, cols = np.mgrid[r0 : r0 + window.shape[0], c0 : c0 + window.shape[1]]
        score = window - 0.4 * np.hypot(rows - row, cols - col) * cell
        score[window <= 0] = -np.inf
        k = np.unravel_index(int(np.argmax(score)), score.shape)
        if not np.isfinite(score[k]):
            return point
        return (x0 + (cols[k] + 0.5) * cell, y1 - (rows[k] + 0.5) * cell)

    def is_clear(p, q):
        steps = int(math.hypot(q[0] - p[0], q[1] - p[1]) / (cell * 0.5)) + 2
        xs = np.linspace(p[0], q[0], steps)
        ys = np.linspace(p[1], q[1], steps)
        cols = np.clip(((xs - x0) / cell).astype(np.int64), 0, walkable.shape[1] - 1)
        rows = np.clip(((y1 - ys) / cell).astype(np.int64), 0, walkable.shape[0] - 1)
        return bool(walkable[rows, cols].all())

    positions, polylines = skeleton_graph(skeleton, to_world)
    graph = DraftGraph(positions, polylines)
    graph.drop_short_loops(1.2)
    graph.prune_spurs(spur, clearance_at, room_at)
    graph.merge_degree_two()
    graph.collapse_short(0.6)
    graph.merge_degree_two()
    for _ in range(3):
        graph.drop_thin_cycles()
        graph.prune_spurs(spur, clearance_at, room_at)
        graph.merge_degree_two()
    graph.drop_short_loops(1.2)
    graph.drop_small_components(3.0)
    graph.merge_degree_two()
    graph.centre_junctions(best_point)
    graph.trim_dead_ends(room_at)

    vertices, vertex_of_node, edge_pairs = [], {}, set()

    def vertex_for(point, node=None):
        if node is not None and node in vertex_of_node:
            return vertex_of_node[node]
        vertices.append((float(point[0]), float(point[1]), elevation))
        index = len(vertices) - 1
        if node is not None:
            vertex_of_node[node] = index
        return index

    for a, b, points in graph.edges.values():
        simple = simplify_polyline(straighten(points, is_clear), tolerance)
        chain = [vertex_for(graph.pos[a], a)] + [vertex_for(p) for p in simple[1:-1]] + [vertex_for(graph.pos[b], b)]
        for u, v in zip(chain, chain[1:]):
            if u != v:
                edge_pairs.add((min(u, v), max(u, v)))

    mesh = bpy.data.meshes.new(f"NavGraph {floor_code}")
    mesh.from_pydata(vertices, sorted(edge_pairs), [])
    mesh.attributes.new("ism_not_accessible", "BOOLEAN", "EDGE")
    mesh.attributes.new("ism_closed", "BOOLEAN", "EDGE")
    obj = bpy.data.objects.new(f"NavGraph {floor_code}", mesh)
    obj["ism_role"] = "graph"
    obj["ism_floor"] = floor_code
    obj.show_in_front = True
    obj.color = (0.85, 0.15, 0.24, 1.0)
    floor["collection"].objects.link(obj)
    result = {"floor": floor_code, "vertices": len(vertices), "edges": len(edge_pairs), "walkable_m2": round(float(walkable.sum()) * cell * cell, 1)}
    log(f"draft graph {floor_code}: {result['vertices']} vertices, {result['edges']} edges over {result['walkable_m2']} m2 walkable")
    return result


# ---------------------------------------------------------------------------
# Markers: QR checkpoints, shop entrances, connector stops, facilities
# ---------------------------------------------------------------------------

MARKER_DEFAULTS = {
    "checkpoint": {"ism_code": PLACEHOLDER, "ism_label": PLACEHOLDER, "ism_qr_width_m": 0.18},
    "shop_entrance": {"ism_unit_code": PLACEHOLDER, "ism_primary": True},
    "connector_stop": {"ism_connector": PLACEHOLDER, "ism_connector_type": "elevator", "ism_direction": "both", "ism_accessible": True},
    "facility": {"ism_facility_name": PLACEHOLDER, "ism_facility_type": "toilet"},
}
MARKER_DISPLAY = {"checkpoint": "SINGLE_ARROW", "shop_entrance": "CONE", "connector_stop": "CUBE", "facility": "SPHERE"}


def add_marker(kind, location, yaw=0.0, properties=None):
    """Create a marker empty with its custom properties in the right floor collection.

    For a checkpoint the arrow (local +Z) points out of the QR code's face,
    toward where the shopper stands; ``yaw`` sets that direction.
    """
    if kind not in MARKER_KINDS:
        raise PipelineError(f"Unknown marker kind '{kind}'.")
    floor = floor_for_height(location[2])
    obj = bpy.data.objects.new(f"{kind} {floor['code']}", None)
    obj.empty_display_type = MARKER_DISPLAY[kind]
    obj.empty_display_size = 0.5 if kind == "checkpoint" else 0.35
    obj.location = location
    if kind == "checkpoint":
        obj.rotation_euler = (0.0, math.pi / 2, math.radians(yaw))
    else:
        obj.rotation_euler = (0.0, 0.0, math.radians(yaw))
    obj["ism_kind"] = kind
    for key, value in MARKER_DEFAULTS[kind].items():
        obj[key] = value
    for key, value in (properties or {}).items():
        obj[key] = value
    label = obj.get("ism_code") or obj.get("ism_unit_code") or obj.get("ism_connector") or obj.get("ism_facility_name")
    if label and label != PLACEHOLDER:
        obj.name = f"{kind} {label} {floor['code']}"
    floor["collection"].objects.link(obj)
    bpy.context.view_layer.update()
    return obj


def markers():
    """Every marker object in the file."""
    return [o for o in bpy.data.objects if o.get("ism_kind") in MARKER_KINDS]


# ---------------------------------------------------------------------------
# Building the package: graph, markers, connectors, validation
# ---------------------------------------------------------------------------


class Report:
    """Errors stop an export; warnings are things to look at."""

    def __init__(self):
        self.errors = []
        self.warnings = []

    def error(self, message):
        self.errors.append(message)

    def warn(self, message):
        self.warnings.append(message)

    def as_dict(self):
        return {"ok": not self.errors, "errors": self.errors, "warnings": self.warnings}


class MapGraph:
    """The whole-mall graph assembled from every floor."""

    def __init__(self):
        self.nodes = []
        self.walk = {}

    def add_node(self, floor, x, y, z, node_type="junction", **meta):
        self.nodes.append({"floor": floor, "x": float(x), "y": float(y), "z": float(z), "type": node_type, "code": None, **meta})
        return len(self.nodes) - 1

    def add_walk(self, i, j, accessible=True, closed=False):
        if i == j:
            return
        self.walk[(min(i, j), max(i, j))] = {"accessible": accessible, "closed": closed}

    def floor_segments(self, floor):
        keys = [k for k in self.walk if self.nodes[k[0]]["floor"] == floor]
        a = np.array([[self.nodes[i]["x"], self.nodes[i]["y"]] for i, _ in keys], dtype=np.float64).reshape(-1, 2)
        b = np.array([[self.nodes[j]["x"], self.nodes[j]["y"]] for _, j in keys], dtype=np.float64).reshape(-1, 2)
        return keys, a, b

    def split(self, key, point, floor, z):
        attrs = self.walk.pop(key)
        k = self.add_node(floor, point[0], point[1], z)
        self.add_walk(key[0], k, **attrs)
        self.add_walk(k, key[1], **attrs)
        return k


def read_floor_graph(graph, floor, report):
    """Copy a floor's graph mesh into the mall graph, merging vertices closer than 5 cm."""
    obj = floor["graph"]
    if obj is None:
        report.error(f"Floor {floor['code']} has no navigation graph. Run draft-graph or draw a mesh named 'NavGraph {floor['code']}' with ism_role=graph.")
        return
    mesh = obj.data
    co = vertex_array(mesh, obj.matrix_world)
    if len(mesh.edges) == 0:
        report.error(f"The graph of floor {floor['code']} has no edges.")
        return
    edges = np.empty(len(mesh.edges) * 2, dtype=np.int64)
    mesh.edges.foreach_get("vertices", edges)
    edges = edges.reshape(-1, 2)

    def edge_flags(name):
        attribute = mesh.attributes.get(name)
        if attribute is None or attribute.domain != "EDGE" or attribute.data_type != "BOOLEAN":
            return np.zeros(len(edges), dtype=bool)
        values = np.zeros(len(edges), dtype=bool)
        attribute.data.foreach_get("value", values)
        return values

    not_accessible = edge_flags("ism_not_accessible")
    closed = edge_flags("ism_closed")
    tree = KDTree(len(co))
    for i, p in enumerate(co):
        tree.insert(p, i)
    tree.balance()
    mapping = {}
    used = set(edges.ravel().tolist())
    dropped = len(co) - len(used)
    if dropped:
        report.warn(f"Floor {floor['code']}: {dropped} graph vertices without edges were ignored.")
    for i in sorted(used):
        if i in mapping:
            continue
        node = graph.add_node(floor["code"], co[i, 0], co[i, 1], floor["elevation"])
        for _, j, _ in tree.find_range(co[i], MERGE_M):
            if j in used and j not in mapping:
                mapping[j] = node
        mapping[i] = node
    off_level = np.abs(co[sorted(used), 2] - floor["elevation"]) > 0.5
    if off_level.any():
        report.warn(f"Floor {floor['code']}: {int(off_level.sum())} graph vertices are more than 0.5 m from the floor level; they were placed on the floor.")
    for k, (u, v) in enumerate(edges):
        graph.add_walk(mapping[int(u)], mapping[int(v)], accessible=not bool(not_accessible[k]), closed=bool(closed[k]))


def scan_tree(floor, cache):
    """BVH tree of a floor's scan, for obstruction checks."""
    if floor["code"] not in cache and floor["scan"] is not None:
        depsgraph = bpy.context.evaluated_depsgraph_get()
        cache[floor["code"]] = BVHTree.FromObject(floor["scan"], depsgraph)
    return cache.get(floor["code"])


def blocked(tree, start, end, heights, elevation):
    """True when the scan surface blocks the straight line at any of the heights."""
    if tree is None:
        return False
    delta = Vector((end[0] - start[0], end[1] - start[1], 0.0))
    distance = delta.length
    if distance < 0.2:
        return False
    direction = delta.normalized()
    for h in heights:
        origin = Vector((start[0], start[1], elevation + h))
        hit = tree.ray_cast(origin, direction, distance - 0.1)
        if hit[0] is not None:
            return True
    return False


def attach(graph, floor, point, kind, label, report, trees):
    """Give a marker its own node on the floor graph and connect it.

    Snaps to a nearby plain vertex or edge, otherwise adds a link to the
    nearest point of the graph. Returns the node index or None.
    """
    keys, a, b = graph.floor_segments(floor["code"])
    if not keys:
        return None
    p = np.asarray(point[:2], dtype=np.float64)
    ab = b - a
    t = np.clip(np.einsum("ij,ij->i", p - a, ab) / np.maximum(np.einsum("ij,ij->i", ab, ab), 1e-12), 0.0, 1.0)
    q = a + t[:, None] * ab
    distance = np.linalg.norm(q - p, axis=1)
    k = int(np.argmin(distance))
    key, nearest, gap = keys[k], q[k], float(distance[k])
    z = floor["elevation"]

    def endpoint_or_split():
        for end in key:
            node = graph.nodes[end]
            if math.hypot(node["x"] - nearest[0], node["y"] - nearest[1]) <= SNAP_M:
                return end
        return graph.split(key, nearest, floor["code"], z)

    if gap <= SNAP_M:
        anchor = endpoint_or_split()
        if graph.nodes[anchor]["type"] == "junction":
            return anchor
        taken = graph.nodes[anchor]
        if math.hypot(taken["x"] - p[0], taken["y"] - p[1]) < 0.05:
            report.error(f"{label} sits on the same spot as another marker on floor {floor['code']}; move one of them.")
            return None
        node = graph.add_node(floor["code"], p[0], p[1], z)
        graph.add_walk(node, anchor)
        return node
    if gap > setting("ism_link_max_m"):
        report.error(f"{label} is {gap:.1f} m from the nearest graph edge on floor {floor['code']} (limit {setting('ism_link_max_m'):.1f} m). Draw a graph edge closer to it.")
        return None
    anchor = endpoint_or_split()
    node = graph.add_node(floor["code"], p[0], p[1], z)
    graph.add_walk(node, anchor)
    if blocked(scan_tree(floor, trees), p, nearest, (0.5, 1.2), z):
        report.warn(f"{label}: the scan blocks the straight link to the graph ({gap:.1f} m). Check it in the top view and draw a better edge if needed.")
    return node


def marker_floor(obj, floor_list):
    """Floor of a marker from its height."""
    return floor_for_height(obj.matrix_world.translation.z, floor_list)


def build_package(out_dir=None, metres_per_pixel=0.05, glb_triangles=120_000, max_texture=2048, draco=False):
    """Validate the authored map and, when out_dir is given and there are no errors, write the package.

    Returns (package dict or None, report dict).
    """
    ensure_settings()
    bpy.context.view_layer.update()
    report = Report()
    floor_list = floors()
    if not floor_list:
        report.error("There are no floors. Import a scan first (prepare).")
        return None, report.as_dict()
    codes = [f["code"] for f in floor_list]
    elevations = [f["elevation"] for f in floor_list]
    if len(set(elevations)) != len(elevations):
        report.error("Two floors share the same elevation.")
    for f in floor_list:
        if f["scan"] is None:
            report.error(f"Floor {f['code']} has no scan object (ism_role=scan).")

    graph = MapGraph()
    for f in floor_list:
        read_floor_graph(graph, f, report)
    trees = {}

    checkpoints, entrances, facilities = [], [], []
    stops = defaultdict(list)
    seen_codes = Counter()
    for obj in sorted(markers(), key=lambda o: o.name):
        kind = obj["ism_kind"]
        floor = marker_floor(obj, floor_list)
        location = obj.matrix_world.translation
        label = f"{kind.replace('_', ' ')} '{obj.name}'"

        if kind == "checkpoint":
            code = str(obj.get("ism_code", ""))
            if code == PLACEHOLDER or not CODE_PATTERN.match(code):
                report.error(f"{label}: set ism_code to the code printed on the QR label, for example {floor['code']}-C01 (letters, digits, dot, dash, underscore; up to 50).")
                continue
            seen_codes[code] += 1
            text = str(obj.get("ism_label", ""))
            if not text or text == PLACEHOLDER:
                report.error(f"{label}: set ism_label, for example 'Pillar P3, south face'.")
                continue
            facing = obj.matrix_world.to_3x3() @ Vector((0.0, 0.0, 1.0))
            horizontal = Vector((facing.x, facing.y, 0.0))
            if horizontal.length < 0.7:
                report.error(f"{label}: the arrow must point sideways out of the QR face, not up or down.")
                continue
            normal = horizontal.normalized()
            width = float(obj.get("ism_qr_width_m", 0.18))
            if not 0.05 <= width <= 1.0:
                report.error(f"{label}: ism_qr_width_m must be between 0.05 and 1.0 m.")
                continue
            height = location.z - floor["elevation"]
            if not 0.3 <= height <= 2.5:
                report.warn(f"{label}: the QR centre is {height:.2f} m above the floor; 1.2 to 1.6 m is easiest to scan.")
            tree = scan_tree(floor, trees)
            if tree is not None:
                behind = tree.ray_cast(location + normal * 0.02, -normal, 0.35)
                ahead = tree.ray_cast(location + normal * 0.05, normal, 0.6)
                if behind[0] is None:
                    report.warn(f"{label}: no wall or pillar surface within 0.35 m behind the QR; move it onto the surface.")
                if ahead[0] is not None:
                    report.warn(f"{label}: a surface is right in front of the QR; the arrow may point into the pillar (rotate it 180 degrees).")
            standoff = setting("ism_checkpoint_standoff_m")
            stand = (location.x + normal.x * standoff, location.y + normal.y * standoff)
            node = attach(graph, floor, stand, kind, label, report, trees)
            if node is None:
                continue
            graph.nodes[node].update(type="checkpoint", code=code)
            checkpoints.append(
                {
                    "checkpoint_code": code,
                    "label": text[:120],
                    "floor_code": floor["code"],
                    "node": node,
                    "qr": {
                        "x_m": round(location.x, 4),
                        "y_m": round(location.y, 4),
                        "z_m": round(location.z, 4),
                        "yaw_deg": round(yaw_deg(normal), 3),
                        "marker_height_m": round(height, 4),
                        "physical_width_m": round(width, 4),
                    },
                }
            )

        elif kind == "shop_entrance":
            unit = str(obj.get("ism_unit_code", ""))
            if unit == PLACEHOLDER or not unit or len(unit) > 40:
                report.error(f"{label}: set ism_unit_code to the shop unit code used in the app (up to 40 characters).")
                continue
            node = attach(graph, floor, (location.x, location.y), kind, label, report, trees)
            if node is None:
                continue
            graph.nodes[node].update(type="entrance", unit=unit)
            entrances.append({"unit_code": unit, "floor_code": floor["code"], "node": node, "is_primary": bool(obj.get("ism_primary", True)), "label": label})

        elif kind == "facility":
            name = str(obj.get("ism_facility_name", ""))
            facility_type = str(obj.get("ism_facility_type", ""))
            if name == PLACEHOLDER or not name or len(name) > 120:
                report.error(f"{label}: set ism_facility_name to the facility's name in the app.")
                continue
            if facility_type not in FACILITY_TYPES:
                report.error(f"{label}: ism_facility_type must be one of {', '.join(FACILITY_TYPES)}.")
                continue
            node = attach(graph, floor, (location.x, location.y), kind, label, report, trees)
            if node is None:
                continue
            graph.nodes[node].update(type="facility", name=name)
            facilities.append({"facility_name": name, "facility_type": facility_type, "floor_code": floor["code"], "node": node})

        elif kind == "connector_stop":
            name = str(obj.get("ism_connector", ""))
            connector_type = str(obj.get("ism_connector_type", ""))
            direction = str(obj.get("ism_direction", "both"))
            if name == PLACEHOLDER or not name or len(name) > 100:
                report.error(f"{label}: set ism_connector to the connector's name, the same on every floor (for example 'East Lift A').")
                continue
            if connector_type not in CONNECTOR_TYPES:
                report.error(f"{label}: ism_connector_type must be one of {', '.join(CONNECTOR_TYPES)}.")
                continue
            if direction not in DIRECTIONS:
                report.error(f"{label}: ism_direction must be one of {', '.join(DIRECTIONS)}.")
                continue
            node = attach(graph, floor, (location.x, location.y), kind, label, report, trees)
            if node is None:
                continue
            graph.nodes[node].update(type="connector", connector=name)
            stops[name].append({"floor": floor, "node": node, "type": connector_type, "direction": direction, "accessible": bool(obj.get("ism_accessible", connector_type == "elevator")), "location": (location.x, location.y)})

    for code, count in seen_codes.items():
        if count > 1:
            report.error(f"Checkpoint code {code} is used {count} times; every QR face needs its own code.")

    primaries = defaultdict(list)
    for e in entrances:
        primaries[e["unit_code"]].append(e)
    for unit, items in primaries.items():
        marked = [e for e in items if e["is_primary"]]
        if len(marked) > 1:
            report.error(f"Shop unit {unit} has {len(marked)} primary entrances; set ism_primary to False on all but one.")
        elif not marked:
            items[0]["is_primary"] = True
            report.warn(f"Shop unit {unit} had no primary entrance; {items[0]['label']} was made primary.")

    connector_edges, connectors = [], []
    walk_speed = setting("ism_walk_mps")
    for name, items in sorted(stops.items()):
        types = {s["type"] for s in items}
        if len(types) > 1:
            report.error(f"Connector '{name}' has stops of different types ({', '.join(sorted(types))}).")
            continue
        connector_type = types.pop()
        floors_used = [s["floor"]["code"] for s in items]
        if len(items) < 2 or len(set(floors_used)) != len(floors_used):
            report.error(f"Connector '{name}' needs exactly one stop on each floor it serves and at least two floors (has {', '.join(floors_used)}).")
            continue
        items.sort(key=lambda s: s["floor"]["elevation"])
        directions = {s["direction"] for s in items}
        direction = directions.pop() if len(directions) == 1 else None
        if direction is None:
            report.error(f"Connector '{name}' has different ism_direction values on its stops.")
            continue
        if connector_type == "escalator" and direction == "both":
            report.error(f"Escalator '{name}' must run 'up' or 'down'; model the other direction as a separate escalator.")
            continue
        accessible = all(s["accessible"] for s in items)
        if connector_type != "elevator" and accessible:
            report.warn(f"{connector_type.capitalize()} '{name}' is marked accessible; wheelchair routes will use it.")
        pairs = [(i, j) for i in range(len(items)) for j in range(i + 1, len(items))] if connector_type == "elevator" else [(i, i + 1) for i in range(len(items) - 1)]
        for i, j in pairs:
            low, high = items[i], items[j]
            rise = high["floor"]["elevation"] - low["floor"]["elevation"]
            run = math.dist(low["location"], high["location"])
            if connector_type == "elevator":
                length = rise
                seconds = setting("ism_elevator_wait_s") + rise / setting("ism_elevator_mps")
            elif connector_type == "escalator":
                length = math.hypot(run, rise)
                seconds = length / setting("ism_escalator_mps")
            else:
                length = math.hypot(run, rise)
                seconds = rise / setting("ism_stairs_vertical_mps") + run / walk_speed
            if direction in ("both", "up"):
                connector_edges.append((low["node"], high["node"], length, seconds, accessible, name))
            if direction in ("both", "down"):
                connector_edges.append((high["node"], low["node"], length, seconds, accessible, name))
        connectors.append(
            {
                "name": name,
                "connector_type": connector_type,
                "direction": direction,
                "is_accessible": accessible,
                "stops": [{"floor_code": s["floor"]["code"], "node": s["node"]} for s in items],
            }
        )

    directed = []
    for (i, j), attrs in graph.walk.items():
        ni, nj = graph.nodes[i], graph.nodes[j]
        length = math.hypot(ni["x"] - nj["x"], ni["y"] - nj["y"])
        if length < 0.01:
            report.warn(f"A zero-length edge on floor {ni['floor']} was ignored.")
            continue
        for u, v in ((i, j), (j, i)):
            directed.append((u, v, length, length / walk_speed, attrs["accessible"], None, attrs["closed"]))
    for u, v, length, seconds, accessible, name in connector_edges:
        directed.append((u, v, length, seconds, accessible, name, False))

    used_nodes = sorted({u for u, *_ in directed} | {v for _, v, *_ in directed})
    if used_nodes:
        index = {n: k for k, n in enumerate(used_nodes)}
        pairs = np.array([(index[u], index[v]) for u, v, *_ in directed], dtype=np.int64)
        labels = union_find(len(used_nodes), pairs[:, 0], pairs[:, 1])
        sizes = Counter(labels.tolist())
        if len(sizes) > 1:
            pieces = []
            for label_id, size in sizes.most_common():
                sample = graph.nodes[used_nodes[int(np.flatnonzero(labels == label_id)[0])]]
                pieces.append(f"{size} nodes near ({sample['x']:.1f}, {sample['y']:.1f}) on {sample['floor']}")
            report.error("The graph is split into separate pieces: " + "; ".join(pieces) + ". Join them with edges or connector stops.")

    outgoing = defaultdict(list)
    for u, v, *_ in directed:
        outgoing[u].append(v)

    def reachable(start):
        seen = {start}
        queue = deque([start])
        while queue:
            n = queue.popleft()
            for m in outgoing[n]:
                if m not in seen:
                    seen.add(m)
                    queue.append(m)
        return seen

    destinations = [(e["node"], f"shop unit {e['unit_code']}") for e in entrances] + [(f["node"], f"facility {f['facility_name']}") for f in facilities]
    for c in checkpoints:
        seen = reachable(c["node"])
        missing = [label for node, label in destinations if node not in seen]
        if missing:
            report.error(f"From checkpoint {c['checkpoint_code']} these cannot be reached (check escalator directions): {', '.join(missing[:8])}.")

    if not checkpoints:
        report.warn("No QR checkpoints placed yet.")
    if not entrances:
        report.warn("No shop entrances placed yet.")

    assign_codes(graph, used_nodes, codes)

    if report.errors or out_dir is None:
        return None, report.as_dict()

    floor_entries = write_floor_assets(out_dir, floor_list, graph, used_nodes, directed, metres_per_pixel, glb_triangles, max_texture, draco)

    def code(n):
        return graph.nodes[n]["code"]

    accessible_nodes = {u for u, _, _, _, ok, _, _ in directed if ok} | {v for _, v, _, _, ok, _, _ in directed if ok}
    package = {
        "format": FORMAT,
        "format_version": FORMAT_VERSION,
        "generated_at": datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds"),
        "generator": {"tool": "tools/blender/ism_map.py", "version": ".".join(map(str, bl_info["version"])), "blender": bpy.app.version_string},
        "source_blend": os.path.basename(bpy.data.filepath) if bpy.data.filepath else None,
        "coordinate_frame": COORDINATE_FRAME,
        "glb_axes": GLB_AXES,
        "plan_transform": PLAN_TRANSFORM,
        "settings": {key: setting(key) for key in SETTINGS},
        "floors": floor_entries,
        "nodes": [
            {
                "node_code": code(n),
                "floor_code": graph.nodes[n]["floor"],
                "node_type": graph.nodes[n]["type"],
                "x_m": round(graph.nodes[n]["x"], 4),
                "y_m": round(graph.nodes[n]["y"], 4),
                "z_m": round(graph.nodes[n]["z"], 4),
                "is_accessible": n in accessible_nodes,
            }
            for n in sorted(used_nodes, key=lambda n: (codes.index(graph.nodes[n]["floor"]), code(n)))
        ],
        "edges": [
            {
                "from_node_code": code(u),
                "to_node_code": code(v),
                "length_m": round(length, 3),
                "expected_seconds": round(seconds, 2),
                "is_accessible": bool(ok),
                "status": "temporarily_closed" if closed else "open",
                "connector": name,
            }
            for u, v, length, seconds, ok, name, closed in sorted(directed, key=lambda e: (code(e[0]), code(e[1])))
        ],
        "connectors": [
            {**{k: v for k, v in c.items() if k != "stops"}, "stops": [{"floor_code": s["floor_code"], "node_code": code(s["node"])} for s in c["stops"]]}
            for c in connectors
        ],
        "checkpoints": [{**{k: v for k, v in c.items() if k != "node"}, "node_code": code(c["node"])} for c in sorted(checkpoints, key=lambda c: c["checkpoint_code"])],
        "shop_entrances": [
            {"unit_code": e["unit_code"], "floor_code": e["floor_code"], "node_code": code(e["node"]), "is_primary": e["is_primary"]}
            for e in sorted(entrances, key=lambda e: (e["unit_code"], code(e["node"])))
        ],
        "facility_anchors": [
            {"facility_name": f["facility_name"], "facility_type": f["facility_type"], "floor_code": f["floor_code"], "node_code": code(f["node"])}
            for f in sorted(facilities, key=lambda f: f["facility_name"])
        ],
        "scans": {f["code"]: json.loads(f["scan"].get("ism_prepare", "{}")) for f in floor_list if f["scan"] is not None},
    }
    package["stats"] = {
        "floors": len(package["floors"]),
        "nodes": len(package["nodes"]),
        "edges": len(package["edges"]),
        "checkpoints": len(package["checkpoints"]),
        "shop_entrances": len(package["shop_entrances"]),
        "facility_anchors": len(package["facility_anchors"]),
        "connectors": len(package["connectors"]),
    }
    with open(os.path.join(out_dir, "map_package.json"), "w", encoding="utf-8") as handle:
        json.dump(package, handle, indent=2)
    return package, report.as_dict()


def assign_codes(graph, used_nodes, floor_codes):
    """Give every node a stable, readable code that is unique in the package."""
    taken = set()

    def claim(candidate):
        candidate = candidate[:50]
        base, n = candidate, 2
        while candidate in taken:
            suffix = f"-{n}"
            candidate = base[: 50 - len(suffix)] + suffix
            n += 1
        taken.add(candidate)
        return candidate

    order = {code: i for i, code in enumerate(floor_codes)}
    special = [n for n in used_nodes if graph.nodes[n]["type"] != "junction"]
    for n in special:
        node = graph.nodes[n]
        if node["type"] == "checkpoint":
            node["code"] = claim(node["code"])
        elif node["type"] == "entrance":
            node["code"] = claim(f"E-{slug(node['unit'], 40)}")
        elif node["type"] == "connector":
            node["code"] = claim(f"{node['floor']}-X-{slug(node['connector'])}")
        elif node["type"] == "facility":
            node["code"] = claim(f"{node['floor']}-F-{slug(node['name'])}")
    junctions = sorted((n for n in used_nodes if graph.nodes[n]["type"] == "junction"), key=lambda n: (order[graph.nodes[n]["floor"]], round(graph.nodes[n]["x"], 2), round(graph.nodes[n]["y"], 2)))
    counter = Counter()
    for n in junctions:
        floor = graph.nodes[n]["floor"]
        counter[floor] += 1
        graph.nodes[n]["code"] = claim(f"{floor}-N{counter[floor]:04d}")


# ---------------------------------------------------------------------------
# Floor assets: plan image, graph preview, glTF model
# ---------------------------------------------------------------------------

PLAN_FLOOR = (0.957, 0.945, 0.918, 1.0)
PLAN_OBSTACLE = (0.227, 0.247, 0.278, 1.0)
PREVIEW_COLOURS = {
    "junction": (0.843, 0.149, 0.239, 1.0),
    "checkpoint": (0.110, 0.388, 0.851, 1.0),
    "entrance": (0.086, 0.584, 0.290, 1.0),
    "connector": (0.494, 0.231, 0.769, 1.0),
    "facility": (0.902, 0.494, 0.098, 1.0),
}


def save_png(rgba, path):
    """Write an (h, w, 4) float image, row 0 at the top, as PNG."""
    height, width = rgba.shape[:2]
    image = bpy.data.images.new("ism_export_tmp", width, height, alpha=True)
    image.pixels.foreach_set(np.ascontiguousarray(np.flipud(rgba), dtype=np.float32).ravel())
    image.filepath_raw = path
    image.file_format = "PNG"
    image.save()
    bpy.data.images.remove(image)


def draw_disc(rgba, cx, cy, radius, colour):
    height, width = rgba.shape[:2]
    for dy in range(-radius, radius + 1):
        for dx in range(-radius, radius + 1):
            if dx * dx + dy * dy <= radius * radius:
                x, y = int(cx) + dx, int(cy) + dy
                if 0 <= x < width and 0 <= y < height:
                    rgba[y, x] = colour


def draw_line(rgba, x0, y0, x1, y1, colour):
    height, width = rgba.shape[:2]
    steps = int(max(abs(x1 - x0), abs(y1 - y0)) * 2) + 2
    xs = np.linspace(x0, x1, steps)
    ys = np.linspace(y0, y1, steps)
    for ox, oy in ((0, 0), (1, 0), (0, 1)):
        cx = np.clip(xs.astype(np.int64) + ox, 0, width - 1)
        cy = np.clip(ys.astype(np.int64) + oy, 0, height - 1)
        rgba[cy, cx] = colour


def write_floor_assets(out_dir, floor_list, graph, used_nodes, directed, mpp, glb_triangles, max_texture, draco):
    """Plan PNG, graph preview PNG and GLB per floor; returns the floor entries."""
    os.makedirs(os.path.join(out_dir, "floors"), exist_ok=True)
    bounds = scan_bounds(floor_list)
    x0, _, _, y1 = bounds
    entries = []
    for f in floor_list:
        code = f["code"]
        log(f"writing assets for floor {code}")
        floor_cells, obstacle = rasterise(f["scan"], f["elevation"], bounds, mpp)
        building = erode(dilate(floor_cells, 2), 2)
        obstacle = solid_obstacles(obstacle, mpp)
        rgba = np.zeros(floor_cells.shape + (4,), dtype=np.float32)
        rgba[building] = PLAN_FLOOR
        rgba[obstacle] = PLAN_OBSTACLE
        height, width = floor_cells.shape
        plan_file = f"floors/{code}_plan.png"
        save_png(rgba, os.path.join(out_dir, plan_file))

        def px(node):
            return (node["x"] - x0) / mpp, (y1 - node["y"]) / mpp

        preview = rgba.copy()
        preview[preview[..., 3] == 0] = (1.0, 1.0, 1.0, 1.0)
        floor_nodes = {n for n in used_nodes if graph.nodes[n]["floor"] == code}
        for u, v, *_ in directed:
            if u in floor_nodes and v in floor_nodes:
                ax, ay = px(graph.nodes[u])
                bx, by = px(graph.nodes[v])
                draw_line(preview, ax, ay, bx, by, PREVIEW_COLOURS["junction"])
        for n in floor_nodes:
            node = graph.nodes[n]
            cx, cy = px(node)
            draw_disc(preview, cx, cy, 2 if node["type"] == "junction" else 5, PREVIEW_COLOURS[node["type"]])
        preview_file = f"floors/{code}_graph_preview.png"
        save_png(preview, os.path.join(out_dir, preview_file))

        model_file = f"floors/{code}_model.glb"
        triangles = export_floor_glb(f["scan"], os.path.join(out_dir, model_file), glb_triangles, max_texture, draco)
        entries.append(
            {
                "floor_code": code,
                "name": f["name"],
                "elevation_m": round(f["elevation"], 4),
                "plan": {
                    "file": plan_file,
                    "width_px": width,
                    "height_px": height,
                    "pixel_origin_x": 0,
                    "pixel_origin_y": 0,
                    "metres_per_pixel": mpp,
                    "rotation_deg": 0,
                    "world_origin_x_m": round(x0, 4),
                    "world_origin_y_m": round(y1, 4),
                },
                "graph_preview_file": preview_file,
                "model": {"file": model_file, "triangles": triangles, "draco": bool(draco)},
            }
        )
    return entries


def export_floor_glb(scan, path, max_triangles, max_texture, draco):
    """Export a reduced copy of the scan as GLB (Y-up). The .blend is not changed on disk."""
    copy = scan.copy()
    copy.data = scan.data.copy()
    bpy.context.scene.collection.objects.link(copy)
    temporary_materials, temporary_images = [], []
    try:
        triangles = decimate_to(copy, max_triangles)
        for slot in copy.material_slots:
            material = slot.material
            if material is None or material.node_tree is None:
                continue
            material = material.copy()
            temporary_materials.append(material)
            slot.material = material
            for node in material.node_tree.nodes:
                image = getattr(node, "image", None)
                if image is not None and max(image.size) > max_texture > 0:
                    factor = max_texture / max(image.size)
                    smaller = image.copy()
                    temporary_images.append(smaller)
                    smaller.scale(max(1, int(image.size[0] * factor)), max(1, int(image.size[1] * factor)))
                    node.image = smaller
        select_only(copy)
        bpy.ops.export_scene.gltf(
            filepath=path,
            export_format="GLB",
            use_selection=True,
            export_yup=True,
            export_apply=True,
            export_draco_mesh_compression_enable=bool(draco),
        )
    finally:
        mesh = copy.data
        bpy.data.objects.remove(copy, do_unlink=True)
        if mesh.users == 0:
            bpy.data.meshes.remove(mesh)
        for material in temporary_materials:
            bpy.data.materials.remove(material)
        for image in temporary_images:
            bpy.data.images.remove(image)
    return triangles


def export_package(out_dir, **options):
    """Validate and write the package; raises PipelineError with the report when invalid."""
    os.makedirs(out_dir, exist_ok=True)
    package, report = build_package(out_dir, **options)
    with open(os.path.join(out_dir, "report.json"), "w", encoding="utf-8") as handle:
        json.dump(report, handle, indent=2)
    print_report(report)
    if package is None:
        raise PipelineError(f"Export stopped: {len(report['errors'])} error(s). See {os.path.join(out_dir, 'report.json')}.")
    log(f"package written to {out_dir}: {package['stats']}")
    return package, report


def print_report(report):
    for message in report["errors"]:
        log(f"ERROR   {message}")
    for message in report["warnings"]:
        log(f"warning {message}")
    log("valid" if report["ok"] else f"{len(report['errors'])} error(s), {len(report['warnings'])} warning(s)")


# ---------------------------------------------------------------------------
# make-test-scan: a synthetic two-floor mall section that looks like a LiDAR export
# ---------------------------------------------------------------------------

WALL = 0.15
STOREY = 3.2


def _grid(bm, origin, u, v, cell, flip=False, rng=None, jitter=0.004):
    """Add a subdivided planar rectangle; normal is u x v (or reversed)."""
    origin, u, v = np.asarray(origin, float), np.asarray(u, float), np.asarray(v, float)
    nu = max(1, int(math.ceil(np.linalg.norm(u) / cell)))
    nv = max(1, int(math.ceil(np.linalg.norm(v) / cell)))
    normal = np.cross(u, v)
    normal = normal / (np.linalg.norm(normal) or 1.0)
    verts = []
    for j in range(nv + 1):
        row = []
        for i in range(nu + 1):
            p = origin + u * (i / nu) + v * (j / nv)
            if rng is not None:
                p = p + normal * rng.normal(0.0, jitter)
            row.append(bm.verts.new(p))
        verts.append(row)
    for j in range(nv):
        for i in range(nu):
            quad = [verts[j][i], verts[j][i + 1], verts[j + 1][i + 1], verts[j + 1][i]]
            bm.faces.new(quad[::-1] if flip else quad)


def _box(bm, x0, x1, y0, y1, z0, z1, cell, rng, bottom=False):
    """Closed-ish box with outward normals."""
    dx, dy, dz = x1 - x0, y1 - y0, z1 - z0
    _grid(bm, (x0, y0, z1), (dx, 0, 0), (0, dy, 0), cell, rng=rng)
    if bottom:
        _grid(bm, (x0, y0, z0), (dx, 0, 0), (0, dy, 0), cell, flip=True, rng=rng)
    _grid(bm, (x0, y0, z0), (dx, 0, 0), (0, 0, dz), cell, rng=rng)
    _grid(bm, (x0, y1, z0), (dx, 0, 0), (0, 0, dz), cell, flip=True, rng=rng)
    _grid(bm, (x0, y0, z0), (0, dy, 0), (0, 0, dz), cell, flip=True, rng=rng)
    _grid(bm, (x1, y0, z0), (0, dy, 0), (0, 0, dz), cell, rng=rng)


def _wall_x(bm, x0, x1, y, gaps, cell, rng):
    """Wall along x at y, leaving door gaps [(a, b), ...]."""
    edges = [x0] + [v for g in sorted(gaps) for v in g] + [x1]
    for a, b in zip(edges[::2], edges[1::2]):
        if b - a > 0.05:
            _box(bm, a, b, y - WALL / 2, y + WALL / 2, 0, STOREY, 0.5, rng)


def _wall_y(bm, y0, y1, x, gaps, cell, rng):
    edges = [y0] + [v for g in sorted(gaps) for v in g] + [y1]
    for a, b in zip(edges[::2], edges[1::2]):
        if b - a > 0.05:
            _box(bm, x - WALL / 2, x + WALL / 2, a, b, 0, STOREY, 0.5, rng)


TEST_LAYOUT = {
    "corridor": (2.0, 62.0, 14.0, 20.0),
    "side_corridor": (30.0, 34.0, 20.0, 32.0),
    "lift": (30.0, 34.0, 32.0, 34.0),
    "shops": {
        "A": {"rect": (6.0, 16.0, 6.0, 14.0), "door": (10.0, 12.0), "side": "south"},
        "B": {"rect": (20.0, 30.0, 6.0, 14.0), "door": (24.0, 26.0), "side": "south"},
        "C": {"rect": (40.0, 50.0, 20.0, 28.0), "door": (44.0, 46.0), "side": "north", "floors": ["G"]},
    },
    "pillars_x": [8.0, 18.0, 28.0, 38.0, 48.0, 58.0],
    "pillars_y": [15.0, 19.0],
    "pillar_half": 0.3,
    "bench": {"rect": (44.0, 46.0, 16.8, 17.2), "height": 0.45, "floors": ["G"]},
    "junction": (32.0, 17.0),
}


def build_test_floor(floor_code, rng, cell=0.25):
    """Geometry of one synthetic floor in its true (canonical) frame, floor at z=0."""
    layout = TEST_LAYOUT
    bm = bmesh.new()
    cx0, cx1, cy0, cy1 = layout["corridor"]
    sx0, sx1, sy0, sy1 = layout["side_corridor"]
    floors_rects = [layout["corridor"], layout["side_corridor"]]
    south_doors, north_doors = [], [(sx0, sx1)]
    for shop in layout["shops"].values():
        if floor_code not in shop.get("floors", ["G", "1"]):
            continue
        floors_rects.append(shop["rect"])
        (south_doors if shop["side"] == "south" else north_doors).append(shop["door"])
        x0, x1, y0, y1 = shop["rect"]
        far = y0 if shop["side"] == "south" else y1
        _wall_x(bm, x0, x1, far, [], cell, rng)
        _wall_y(bm, y0, y1, x0, [], cell, rng)
        _wall_y(bm, y0, y1, x1, [], cell, rng)
        _grid(bm, (x0, y0, STOREY), (x1 - x0, 0, 0), (0, y1 - y0, 0), 0.5, flip=True, rng=rng)
    for x0, x1, y0, y1 in floors_rects:
        _grid(bm, (x0, y0, 0.0), (x1 - x0, 0, 0), (0, y1 - y0, 0), cell, rng=rng)
    _grid(bm, (cx0, cy0, STOREY), (cx1 - cx0, 0, 0), (0, cy1 - cy0, 0), 0.5, flip=True, rng=rng)
    _grid(bm, (sx0, sy0, STOREY), (sx1 - sx0, 0, 0), (0, sy1 - sy0, 0), 0.5, flip=True, rng=rng)
    _wall_x(bm, cx0, cx1, cy0, south_doors, cell, rng)
    _wall_x(bm, cx0, cx1, cy1, north_doors, cell, rng)
    _wall_y(bm, cy0, cy1, cx0, [], cell, rng)
    _wall_y(bm, cy0, cy1, cx1, [], cell, rng)
    _wall_y(bm, sy0, sy1, sx0, [], cell, rng)
    _wall_y(bm, sy0, sy1, sx1, [], cell, rng)
    lx0, lx1, ly0, ly1 = layout["lift"]
    _box(bm, lx0, lx1, ly0, ly1, 0.0, STOREY, 0.5, rng)
    half = layout["pillar_half"]
    for px in layout["pillars_x"]:
        for py in layout["pillars_y"]:
            _box(bm, px - half, px + half, py - half, py + half, 0.0, STOREY, 0.25, rng)
    bench = layout["bench"]
    if floor_code in bench["floors"]:
        bx0, bx1, by0, by1 = bench["rect"]
        _box(bm, bx0, bx1, by0, by1, 0.0, bench["height"], 0.2, rng)
    for _ in range(40):
        centre = np.array([rng.uniform(4, 60), rng.uniform(8, 30), rng.uniform(0.6, 2.8)])
        size = rng.uniform(0.03, 0.08)
        verts = [bm.verts.new(centre + rng.normal(0, size, 3)) for _ in range(3)]
        bm.faces.new(verts)
    return bm


def make_test_scan(out_dir, seed=7):
    """Write two synthetic 'LiDAR exports' (floors G and 1) plus truth.json for tests.

    Each floor is rotated and moved as if scanned in the phone's own frame,
    and saved as Y-up GLB like Polycam or Scaniverse exports.
    """
    os.makedirs(out_dir, exist_ok=True)
    rng = np.random.default_rng(seed)
    scan_frames = {"G": (17.0, (12.3, -4.1, 1.37)), "1": (25.0, (-3.5, 8.2, -0.62))}
    files = {}
    for code, (yaw, shift) in scan_frames.items():
        bm = build_test_floor(code, rng)
        mesh = bpy.data.meshes.new(f"test_scan_{code}")
        bm.to_mesh(mesh)
        bm.free()
        obj = bpy.data.objects.new(f"test_scan_{code}", mesh)
        bpy.context.scene.collection.objects.link(obj)
        co = vertex_array(mesh)
        low = co.min(axis=0)
        mesh.transform(Matrix.Translation((-low[0], -low[1], 0.0)))
        mesh.transform(Matrix.Translation(shift) @ Matrix.Rotation(math.radians(yaw), 4, "Z"))
        material = bpy.data.materials.new(f"scan_material_{code}")
        material.diffuse_color = (0.7, 0.68, 0.62, 1.0)
        mesh.materials.append(material)
        select_only(obj)
        path = os.path.join(out_dir, f"test_scan_{code}.glb")
        bpy.ops.export_scene.gltf(filepath=path, export_format="GLB", use_selection=True, export_yup=True)
        bpy.data.objects.remove(obj, do_unlink=True)
        bpy.data.meshes.remove(mesh)
        files[code] = path
        if code == "G":
            canonical_shift = (-float(low[0]), -float(low[1]))
    truth = {
        "files": files,
        "elevations": {"G": 0.0, "1": 5.0},
        "canonical_shift": canonical_shift,
        "layout": TEST_LAYOUT,
    }
    with open(os.path.join(out_dir, "truth.json"), "w", encoding="utf-8") as handle:
        json.dump(truth, handle, indent=2)
    log(f"test scans written to {out_dir}")
    return truth


# ---------------------------------------------------------------------------
# Command line
# ---------------------------------------------------------------------------


def open_or_new(path):
    """Open a .blend, or start an empty scene when it does not exist yet."""
    if path and os.path.isfile(path):
        bpy.ops.wm.open_mainfile(filepath=path)
    else:
        bpy.ops.wm.read_factory_settings(use_empty=True)
    ensure_settings()


def save(path):
    os.makedirs(os.path.dirname(os.path.abspath(path)), exist_ok=True)
    bpy.ops.wm.save_as_mainfile(filepath=os.path.abspath(path), compress=True)
    log(f"saved {path}")


def parse_pair(text):
    parts = [float(v) for v in text.split(",")]
    if len(parts) != 2:
        raise argparse.ArgumentTypeError("use X,Y")
    return tuple(parts)


def cli(argv):
    parser = argparse.ArgumentParser(prog="blender -b --factory-startup -P tools/blender/ism_map.py --", description="ISM indoor map pipeline")
    sub = parser.add_subparsers(dest="command", required=True)

    p = sub.add_parser("make-test-scan", help="write synthetic two-floor test scans")
    p.add_argument("--out", required=True)
    p.add_argument("--seed", type=int, default=7)

    p = sub.add_parser("prepare", help="import, clean, level and align one floor scan into the .blend")
    p.add_argument("--blend", required=True, help="the map .blend (created if missing)")
    p.add_argument("--scan", required=True)
    p.add_argument("--floor", required=True, help="floor code as in the app, e.g. G or L1")
    p.add_argument("--elevation", type=float, required=True, help="floor level in metres in the mall frame")
    p.add_argument("--name", help="floor name, e.g. 'Ground floor'")
    p.add_argument("--scale", type=float, default=1.0, help="multiply the scan size (fix a wrong scale)")
    p.add_argument("--yaw-deg", type=float, help="rotate by exactly this angle instead of auto-squaring the walls")
    p.add_argument("--yaw-offset", type=float, default=0.0, help="extra rotation after auto-squaring (90, 180, 270 to fix a turned floor)")
    p.add_argument("--keep-xy", action="store_true", help="do not move the scan's lowest corner to (0, 0)")
    p.add_argument("--offset", type=parse_pair, default=(0.0, 0.0), help="extra X,Y shift in metres")
    p.add_argument("--ceiling-cut", type=float, default=2.6, help="drop surfaces higher than this above the floor")
    p.add_argument("--min-fragment", type=float, default=0.5, help="drop floating pieces smaller than this (m)")
    p.add_argument("--max-tris", type=int, default=400_000, help="working triangle budget")
    p.add_argument("--replace", action="store_true", help="replace this floor's existing scan")

    p = sub.add_parser("draft-graph", help="draw a first navigation graph from a floor's walkable area")
    p.add_argument("--blend", required=True)
    p.add_argument("--floor", required=True)
    p.add_argument("--cell", type=float, default=0.1)
    p.add_argument("--clearance", type=float, default=0.35, help="distance kept from walls and obstacles (m)")
    p.add_argument("--spur", type=float, default=1.5, help="remove dead-end branches shorter than this (m)")
    p.add_argument("--replace", action="store_true")

    for name in ("validate", "export"):
        p = sub.add_parser(name, help="check the map" if name == "validate" else "check and write the map package")
        p.add_argument("--blend", required=True)
        if name == "export":
            p.add_argument("--out", required=True)
            p.add_argument("--mpp", type=float, default=0.05, help="plan image metres per pixel")
            p.add_argument("--glb-tris", type=int, default=120_000)
            p.add_argument("--max-texture", type=int, default=2048)
            p.add_argument("--draco", action="store_true", help="Draco-compress the GLB (needs DRACOLoader in the viewer)")

    args = parser.parse_args(argv)
    try:
        if args.command == "make-test-scan":
            bpy.ops.wm.read_factory_settings(use_empty=True)
            make_test_scan(args.out, args.seed)
        elif args.command == "prepare":
            open_or_new(args.blend)
            prepare_scan(
                args.scan,
                args.floor,
                args.elevation,
                floor_name=args.name,
                scale=args.scale,
                yaw=args.yaw_deg,
                yaw_offset=args.yaw_offset,
                keep_xy=args.keep_xy,
                offset=args.offset,
                ceiling_cut=args.ceiling_cut,
                min_fragment=args.min_fragment,
                max_triangles=args.max_tris,
                replace=args.replace,
            )
            save(args.blend)
        elif args.command == "draft-graph":
            if not os.path.isfile(args.blend):
                raise PipelineError(f"{args.blend} does not exist. Run prepare first.")
            open_or_new(args.blend)
            draft_graph(args.floor, cell=args.cell, clearance=args.clearance, spur=args.spur, replace=args.replace)
            save(args.blend)
        elif args.command == "validate":
            open_or_new(args.blend)
            _, report = build_package(None)
            print_report(report)
            return 0 if report["ok"] else 1
        elif args.command == "export":
            open_or_new(args.blend)
            export_package(args.out, metres_per_pixel=args.mpp, glb_triangles=args.glb_tris, max_texture=args.max_texture, draco=args.draco)
    except PipelineError as problem:
        log(f"ERROR {problem}")
        return 1
    return 0


# ---------------------------------------------------------------------------
# Blender user interface (sidebar tab "ISM Map")
# ---------------------------------------------------------------------------


_ENUM_CACHE = []


def _floor_items(self, context):
    """Floor choices for operators; cached so Blender keeps the strings alive."""
    items = [(f["code"], f"{f['code']} ({f['elevation']:g} m)", f["name"]) for f in floors()]
    _ENUM_CACHE[:] = items or [("", "no floors yet", "")]
    return _ENUM_CACHE


class ISM_OT_import_scan(bpy.types.Operator):
    """Import a LiDAR scan as one floor: clean, level, square to the axes"""

    bl_idname = "ism.import_scan"
    bl_label = "Import scan as floor"
    bl_options = {"REGISTER", "UNDO"}

    filepath: bpy.props.StringProperty(subtype="FILE_PATH")
    filter_glob: bpy.props.StringProperty(default="*.glb;*.gltf;*.obj;*.usdz;*.usd;*.usdc;*.ply;*.stl;*.fbx", options={"HIDDEN"})
    floor_code: bpy.props.StringProperty(name="Floor code", default="G")
    floor_name: bpy.props.StringProperty(name="Floor name", default="Ground floor")
    elevation: bpy.props.FloatProperty(name="Elevation (m)", default=0.0)
    yaw_offset: bpy.props.FloatProperty(name="Extra rotation (deg)", default=0.0)
    ceiling_cut: bpy.props.FloatProperty(name="Ceiling cut (m)", default=2.6, min=1.0)
    replace: bpy.props.BoolProperty(name="Replace existing scan", default=False)

    def invoke(self, context, event):
        context.window_manager.fileselect_add(self)
        return {"RUNNING_MODAL"}

    def execute(self, context):
        try:
            prepare_scan(self.filepath, self.floor_code, self.elevation, floor_name=self.floor_name, yaw_offset=self.yaw_offset, ceiling_cut=self.ceiling_cut, replace=self.replace)
        except PipelineError as problem:
            self.report({"ERROR"}, str(problem))
            return {"CANCELLED"}
        self.report({"INFO"}, f"Floor {self.floor_code} imported")
        return {"FINISHED"}


class ISM_OT_draft_graph(bpy.types.Operator):
    """Draw a first navigation graph from the floor's walkable area"""

    bl_idname = "ism.draft_graph"
    bl_label = "Draft graph"
    bl_options = {"REGISTER", "UNDO"}

    floor_code: bpy.props.EnumProperty(name="Floor", items=_floor_items)
    clearance: bpy.props.FloatProperty(name="Clearance (m)", default=0.35, min=0.1, max=1.5)
    replace: bpy.props.BoolProperty(name="Replace existing graph", default=False)

    def invoke(self, context, event):
        return context.window_manager.invoke_props_dialog(self)

    def execute(self, context):
        try:
            result = draft_graph(self.floor_code, clearance=self.clearance, replace=self.replace)
        except PipelineError as problem:
            self.report({"ERROR"}, str(problem))
            return {"CANCELLED"}
        self.report({"INFO"}, f"Graph {result['floor']}: {result['vertices']} vertices, {result['edges']} edges")
        return {"FINISHED"}


class ISM_OT_add_marker(bpy.types.Operator):
    """Add a marker at the 3D cursor"""

    bl_idname = "ism.add_marker"
    bl_label = "Add marker"
    bl_options = {"REGISTER", "UNDO"}

    kind: bpy.props.EnumProperty(
        name="Kind",
        items=[
            ("checkpoint", "QR checkpoint", "A QR code on one pillar face; the arrow points out of the face"),
            ("shop_entrance", "Shop entrance", "The doorway of a shop unit"),
            ("connector_stop", "Lift / escalator / stairs stop", "Where a vertical connector meets this floor"),
            ("facility", "Facility", "Toilet, prayer room, information desk, exit, ATM"),
        ],
    )

    def execute(self, context):
        try:
            obj = add_marker(self.kind, tuple(context.scene.cursor.location))
        except PipelineError as problem:
            self.report({"ERROR"}, str(problem))
            return {"CANCELLED"}
        select_only(obj)
        return {"FINISHED"}


class ISM_OT_validate(bpy.types.Operator):
    """Check the map and list problems in the 'ISM report' text"""

    bl_idname = "ism.validate"
    bl_label = "Validate"

    def execute(self, context):
        _, report = build_package(None)
        text = bpy.data.texts.get("ISM report") or bpy.data.texts.new("ISM report")
        text.clear()
        text.write("ERRORS\n" + "\n".join(report["errors"] or ["none"]) + "\n\nWARNINGS\n" + "\n".join(report["warnings"] or ["none"]) + "\n")
        level = {"INFO"} if report["ok"] else {"ERROR"}
        self.report(level, f"{len(report['errors'])} error(s), {len(report['warnings'])} warning(s); see the 'ISM report' text")
        return {"FINISHED"}


class ISM_OT_export(bpy.types.Operator):
    """Validate and write the map package to the export folder"""

    bl_idname = "ism.export"
    bl_label = "Export package"

    def execute(self, context):
        folder = bpy.path.abspath(context.scene.ism_export_dir)
        if not bpy.data.filepath and folder.startswith("//"):
            self.report({"ERROR"}, "Save the .blend first, or choose an absolute export folder.")
            return {"CANCELLED"}
        try:
            package, _ = export_package(folder)
        except PipelineError as problem:
            self.report({"ERROR"}, str(problem))
            return {"CANCELLED"}
        self.report({"INFO"}, f"Exported {package['stats']['nodes']} nodes to {folder}")
        return {"FINISHED"}


class ISM_PT_map(bpy.types.Panel):
    bl_label = "ISM Map"
    bl_idname = "ISM_PT_map"
    bl_space_type = "VIEW_3D"
    bl_region_type = "UI"
    bl_category = "ISM Map"

    def draw(self, context):
        layout = self.layout
        box = layout.box()
        box.label(text="1. Floors")
        for f in floors():
            row = box.row()
            row.label(text=f"{f['code']}  {f['elevation']:g} m  scan {'yes' if f['scan'] else 'no'}  graph {'yes' if f['graph'] else 'no'}")
        box.operator(ISM_OT_import_scan.bl_idname, icon="IMPORT")
        box.operator(ISM_OT_draft_graph.bl_idname, icon="OUTLINER_DATA_CURVE")

        box = layout.box()
        box.label(text="2. Markers at 3D cursor")
        column = box.column(align=True)
        for kind, label, icon in (("checkpoint", "QR checkpoint", "EMPTY_SINGLE_ARROW"), ("shop_entrance", "Shop entrance", "HOME"), ("connector_stop", "Connector stop", "SORT_DESC"), ("facility", "Facility", "INFO")):
            column.operator(ISM_OT_add_marker.bl_idname, text=label, icon=icon).kind = kind
        active = context.active_object
        if active is not None and active.get("ism_kind") in MARKER_KINDS:
            inner = box.box()
            inner.label(text=f"Selected: {active['ism_kind']}")
            for key in MARKER_DEFAULTS[active["ism_kind"]]:
                inner.prop(active, f'["{key}"]', text=key.replace("ism_", ""))

        box = layout.box()
        box.label(text="3. Check and export")
        box.operator(ISM_OT_validate.bl_idname, icon="CHECKMARK")
        box.prop(context.scene, "ism_export_dir", text="Folder")
        box.operator(ISM_OT_export.bl_idname, icon="EXPORT")


CLASSES = (ISM_OT_import_scan, ISM_OT_draft_graph, ISM_OT_add_marker, ISM_OT_validate, ISM_OT_export, ISM_PT_map)


def register():
    for cls in CLASSES:
        try:
            bpy.utils.register_class(cls)
        except ValueError:
            bpy.utils.unregister_class(cls)
            bpy.utils.register_class(cls)
    bpy.types.Scene.ism_export_dir = bpy.props.StringProperty(
        name="Export folder",
        description="Where Export package writes map_package.json and the floor files (// means next to the .blend)",
        subtype="DIR_PATH",
        default="//map_package",
    )


def unregister():
    for cls in reversed(CLASSES):
        try:
            bpy.utils.unregister_class(cls)
        except RuntimeError:
            pass
    if hasattr(bpy.types.Scene, "ism_export_dir"):
        del bpy.types.Scene.ism_export_dir


if __name__ == "__main__":
    if "--" in sys.argv:
        sys.exit(cli(sys.argv[sys.argv.index("--") + 1 :]))
    register()
