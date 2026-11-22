"""End-to-end test of tools/blender/ism_map.py, run inside Blender.

    blender -b --factory-startup --python-exit-code 1 -P tests/Blender/test_ism_map.py -- [--keep DIR] [--write-sample]

It builds two synthetic floor scans whose true layout is known, runs prepare,
draft-graph, marker placement, validation and export exactly as a user would,
and checks the results against that layout. ``--write-sample`` refreshes
tools/blender/samples/pilot_map_package.json, the contract fixture used by the
PHP tests. Exit code 0 means every check passed.
"""

import argparse
import heapq
import json
import math
import os
import shutil
import sys
import tempfile
import traceback

import bpy
from mathutils import Vector
from mathutils.bvhtree import BVHTree

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
sys.path.insert(0, os.path.join(ROOT, "tools", "blender"))

import ism_map  # noqa: E402

CHECKS = {"passed": 0, "failed": []}


def check(condition, message):
    if condition:
        CHECKS["passed"] += 1
        print(f"  ok   {message}", flush=True)
    else:
        CHECKS["failed"].append(message)
        print(f"  FAIL {message}", flush=True)


def close(a, b, tolerance):
    return abs(a - b) <= tolerance


class Frame:
    """Converts test-layout coordinates to the prepared mall frame."""

    def __init__(self, truth):
        self.dx, self.dy = truth["canonical_shift"]

    def __call__(self, x, y, z=0.0):
        return (x + self.dx, y + self.dy, z)


def dijkstra(package, start, goal):
    """Shortest path by length over the exported directed edges."""
    graph = {}
    for edge in package["edges"]:
        graph.setdefault(edge["from_node_code"], []).append(edge)
    best = {start: 0.0}
    previous = {}
    queue = [(0.0, start)]
    while queue:
        cost, node = heapq.heappop(queue)
        if node == goal:
            path = []
            while node in previous:
                edge = previous[node]
                path.append(edge)
                node = edge["from_node_code"]
            return cost, list(reversed(path))
        if cost > best.get(node, math.inf):
            continue
        for edge in graph.get(node, []):
            nxt = cost + edge["length_m"]
            if nxt < best.get(edge["to_node_code"], math.inf):
                best[edge["to_node_code"]] = nxt
                previous[edge["to_node_code"]] = edge
                heapq.heappush(queue, (nxt, edge["to_node_code"]))
    return math.inf, []


def place_markers(at):
    """The markers a mapper would place for the test layout."""
    add = ism_map.add_marker
    add("checkpoint", at(28.0, 18.695, 1.4), 270, {"ism_code": "G-C01", "ism_label": "Pillar P3 north row, south face"})
    add("checkpoint", at(30.08, 28.0, 1.4), 0, {"ism_code": "G-C02", "ism_label": "Side corridor, west wall by the lift"})
    add("checkpoint", at(30.08, 28.0, 6.4), 0, {"ism_code": "1-C01", "ism_label": "Side corridor, west wall by the lift"})
    add("checkpoint", at(18.0, 18.695, 6.4), 270, {"ism_code": "1-C02", "ism_label": "Pillar P2 north row, south face"})
    add("shop_entrance", at(11.0, 14.0, 0.0), 0, {"ism_unit_code": "G-101"})
    add("shop_entrance", at(45.0, 20.0, 0.0), 0, {"ism_unit_code": "G-201"})
    add("shop_entrance", at(25.0, 14.0, 5.0), 0, {"ism_unit_code": "1-105"})
    add("facility", at(3.0, 17.0, 0.0), 0, {"ism_facility_name": "Toilets G West", "ism_facility_type": "toilet"})
    for z in (0.0, 5.0):
        add("connector_stop", at(32.0, 31.2, z), 0, {"ism_connector": "Central Lift", "ism_connector_type": "elevator", "ism_direction": "both", "ism_accessible": True})
    add("connector_stop", at(55.0, 17.0, 0.0), 0, {"ism_connector": "East Escalator Up", "ism_connector_type": "escalator", "ism_direction": "up", "ism_accessible": False})
    add("connector_stop", at(59.0, 17.0, 5.0), 0, {"ism_connector": "East Escalator Up", "ism_connector_type": "escalator", "ism_direction": "up", "ism_accessible": False})


def graph_arrays(obj):
    mesh = obj.data
    verts = [obj.matrix_world @ v.co for v in mesh.vertices]
    edges = [tuple(e.vertices) for e in mesh.edges]
    return verts, edges


def connected(count, edges):
    parent = list(range(count))

    def find(i):
        while parent[i] != i:
            parent[i] = parent[parent[i]]
            i = parent[i]
        return i

    for a, b in edges:
        parent[find(a)] = find(b)
    used = {i for e in edges for i in e}
    return len({find(i) for i in used}) == 1


def test_prepare(truth, at):
    print("prepare", flush=True)
    meta_g = ism_map.prepare_scan(truth["files"]["G"], "G", 0.0, floor_name="Ground floor")
    meta_1 = ism_map.prepare_scan(truth["files"]["1"], "1", 5.0, floor_name="First floor")
    check(close(meta_g["rotation_deg"], -17.0, 0.2), f"floor G squared to the axes (rotation {meta_g['rotation_deg']})")
    check(close(meta_1["rotation_deg"], -25.0, 0.2), f"floor 1 squared to the axes (rotation {meta_1['rotation_deg']})")
    check(close(meta_g["detected_floor_z_m"], 1.37, 0.02), f"floor G level found ({meta_g['detected_floor_z_m']})")
    check(close(meta_1["detected_floor_z_m"], -0.62, 0.02), f"floor 1 level found ({meta_1['detected_floor_z_m']})")
    check(meta_g["fragments_removed"] >= 35, f"floating scan fragments removed ({meta_g['fragments_removed']})")
    check(meta_g["faces_removed_above_ceiling_cut"] > 0, "ceiling removed above the cut height")

    try:
        ism_map.prepare_scan(truth["files"]["G"], "G", 0.0)
        check(False, "a second scan for the same floor is refused without --replace")
    except ism_map.PipelineError:
        check(True, "a second scan for the same floor is refused without --replace")

    for code, z in (("G", 0.0), ("1", 5.0)):
        scan = next(f["scan"] for f in ism_map.floors() if f["code"] == code)
        tree = BVHTree.FromObject(scan, bpy.context.evaluated_depsgraph_get())
        start = Vector(at(28.0, 17.0, z + 1.0))
        hit = tree.ray_cast(start, Vector((0.0, 1.0, 0.0)), 5.0)
        distance = hit[3] if hit[0] is not None else None
        check(distance is not None and close(distance, 1.7, 0.06), f"floor {code}: pillar face lands where the layout puts it (ray {distance})")
        down = tree.ray_cast(Vector(at(20.0, 17.0, z + 1.0)), Vector((0.0, 0.0, -1.0)), 3.0)
        check(down[0] is not None and close(down[0].z, z, 0.02), f"floor {code}: walking surface at elevation {z}")


def test_draft_graph(at):
    print("draft-graph", flush=True)
    for code in ("G", "1"):
        result = ism_map.draft_graph(code)
        floor = next(f for f in ism_map.floors() if f["code"] == code)
        verts, edges = graph_arrays(floor["graph"])
        check(result["edges"] >= 5, f"floor {code}: draft graph has {result['edges']} edges")
        check(connected(len(verts), edges), f"floor {code}: draft graph is one connected piece")
        degree = {}
        for a, b in edges:
            degree[a] = degree.get(a, 0) + 1
            degree[b] = degree.get(b, 0) + 1
        jx, jy, _ = at(32.0, 17.0)
        junctions = [i for i, d in degree.items() if d >= 3 and math.hypot(verts[i].x - jx, verts[i].y - jy) < 1.5]
        check(bool(junctions), f"floor {code}: a junction where the side corridor meets the main corridor")
        xs = [v.x for v in verts]
        check(max(xs) - min(xs) > 50.0, f"floor {code}: graph spans the 60 m corridor ({max(xs) - min(xs):.1f} m)")
        total = sum((verts[a] - verts[b]).length for a, b in edges)
        check(total > 80.0, f"floor {code}: total graph length {total:.1f} m")
        half = ism_map.TEST_LAYOUT["pillar_half"] + 0.2
        inside = 0
        for px in ism_map.TEST_LAYOUT["pillars_x"]:
            for py in ism_map.TEST_LAYOUT["pillars_y"]:
                cx, cy, _ = at(px, py)
                inside += sum(1 for v in verts if abs(v.x - cx) < half and abs(v.y - cy) < half)
        check(inside == 0, f"floor {code}: no graph vertex inside a pillar ({inside})")
        check(all(close(v.z, floor["elevation"], 1e-6) for v in verts), f"floor {code}: graph lies on the floor level")
        ax0, ax1, ay0, ay1 = ism_map.TEST_LAYOUT["shops"]["A"]["rect"]
        lo, hi = at(ax0, ay0), at(ax1, ay1 - 1.0)
        check(any(lo[0] < v.x < hi[0] and lo[1] < v.y < hi[1] for v in verts), f"floor {code}: the graph goes through the doorway into shop A")
        north = at(0.0, 18.8)[1]
        openings = [at(29.0, 0)[0], at(35.0, 0)[0], at(43.0, 0)[0], at(47.0, 0)[0]]
        cx0, cx1 = at(2.0, 0)[0], at(62.0, 0)[0]
        stray = [v for v in verts if v.y > north and cx0 < v.x < cx1 and not (openings[0] < v.x < openings[1] or openings[2] < v.x < openings[3]) and v.y < at(0.0, 20.0)[1]]
        check(not stray, f"floor {code}: no branches into the gaps between pillars and the wall ({len(stray)})")
    try:
        ism_map.draft_graph("G")
        check(False, "drafting over an edited graph is refused without --replace")
    except ism_map.PipelineError:
        check(True, "drafting over an edited graph is refused without --replace")


def expect_error(fragment, setup, teardown):
    created = setup()
    try:
        _, report = ism_map.build_package(None)
        found = any(fragment in message for message in report["errors"])
        check(found, f"validation refuses: {fragment}")
        if not found:
            print("      errors were:", report["errors"], flush=True)
    finally:
        teardown(created)


def remove(objects):
    for obj in objects if isinstance(objects, list) else [objects]:
        bpy.data.objects.remove(obj, do_unlink=True)


def test_validation(at):
    print("validation", flush=True)
    add = ism_map.add_marker
    expect_error("set ism_code", lambda: add("checkpoint", at(38.0, 18.695, 1.4), 270), remove)
    expect_error("is used 2 times", lambda: add("checkpoint", at(38.0, 18.695, 1.4), 270, {"ism_code": "G-C01", "ism_label": "copy"}), remove)
    expect_error("must run 'up' or 'down'", lambda: [add("connector_stop", at(10.0, 17.0, z), 0, {"ism_connector": "West Escalator", "ism_connector_type": "escalator", "ism_direction": "both"}) for z in (0.0, 5.0)], remove)
    expect_error("from the nearest graph edge", lambda: add("shop_entrance", at(11.0, 0.5, 0.0), 0, {"ism_unit_code": "G-FAR"}), remove)
    expect_error("ism_facility_type must be one of", lambda: add("facility", at(5.0, 17.0, 0.0), 0, {"ism_facility_name": "Kiosk", "ism_facility_type": "kiosk"}), remove)

    lift = [o for o in ism_map.markers() if o.get("ism_connector") == "Central Lift"]
    hidden = []
    for obj in lift:
        hidden.append((obj, obj["ism_kind"]))
        obj["ism_kind"] = "disabled"
    try:
        _, report = ism_map.build_package(None)
        check(any("cannot be reached" in m for m in report["errors"]), "validation refuses: shops unreachable when only an up escalator joins the floors")
    finally:
        for obj, kind in hidden:
            obj["ism_kind"] = kind


def test_export(out_dir, at):
    print("export", flush=True)
    package, report = ism_map.export_package(out_dir)
    check(report["ok"], f"export succeeds ({len(report['warnings'])} warnings)")
    for warning in report["warnings"]:
        print(f"      warning: {warning}", flush=True)
    stats = package["stats"]
    check(stats["floors"] == 2 and stats["checkpoints"] == 4 and stats["shop_entrances"] == 3, f"package counts {stats}")
    check(stats["facility_anchors"] == 1 and stats["connectors"] == 2, "facility and connectors exported")
    check(package["format"] == "ism-map-package" and package["format_version"] == 1, "format header")

    codes = [n["node_code"] for n in package["nodes"]]
    check(len(codes) == len(set(codes)), "node codes are unique")
    check(all(len(c) <= 50 for c in codes), "node codes fit varchar(50)")
    known = set(codes)
    check(all(e["from_node_code"] in known and e["to_node_code"] in known for e in package["edges"]), "every edge joins exported nodes")
    check(all(e["from_node_code"] != e["to_node_code"] for e in package["edges"]), "no edge starts and ends on the same node")
    pairs = [(e["from_node_code"], e["to_node_code"]) for e in package["edges"]]
    check(len(pairs) == len(set(pairs)), "at most one edge per ordered node pair")
    check(all(e["length_m"] > 0 and e["expected_seconds"] > 0 for e in package["edges"]), "lengths and times are positive")

    by_code = {c["checkpoint_code"]: c for c in package["checkpoints"]}
    check(close(by_code["G-C01"]["qr"]["yaw_deg"], 270.0, 0.5), f"G-C01 faces -y (yaw {by_code['G-C01']['qr']['yaw_deg']})")
    yaw = by_code["G-C02"]["qr"]["yaw_deg"]
    check(min(yaw, 360 - yaw) < 0.5, f"G-C02 faces +x (yaw {yaw})")
    check(close(by_code["1-C02"]["qr"]["marker_height_m"], 1.4, 0.01), "marker height is measured from its own floor")
    check(by_code["G-C01"]["node_code"] == "G-C01", "checkpoint node carries the checkpoint code")

    node = {n["node_code"]: n for n in package["nodes"]}
    stand = node[by_code["G-C01"]["node_code"]]
    sx, sy, _ = at(28.0, 18.695 - 0.8)
    check(math.hypot(stand["x_m"] - sx, stand["y_m"] - sy) < 0.35, "shopper stands 0.8 m in front of the QR face")

    escalator = [e for e in package["edges"] if e["connector"] == "East Escalator Up"]
    check(len(escalator) == 1 and node[escalator[0]["from_node_code"]]["floor_code"] == "G", "the up escalator only runs from G to 1")
    lift = [e for e in package["edges"] if e["connector"] == "Central Lift"]
    check(len(lift) == 2 and all(close(e["length_m"], 5.0, 1e-6) for e in lift), "the lift runs both ways over 5 m")
    check(all(e["is_accessible"] for e in lift) and not escalator[0]["is_accessible"], "lift accessible, escalator not")

    entrance = {e["unit_code"]: e["node_code"] for e in package["shop_entrances"]}
    length, path = dijkstra(package, "G-C01", entrance["1-105"])
    connectors = {e["connector"] for e in path if e["connector"]}
    check(30.0 < length < 75.0, f"route G-C01 to shop 1-105 is {length:.1f} m")
    check(bool(connectors), f"that route changes floor using {connectors}")
    length, path = dijkstra(package, "1-C01", entrance["G-101"])
    connectors = {e["connector"] for e in path if e["connector"]}
    check(length < math.inf and connectors == {"Central Lift"}, f"going down uses the lift, not the up escalator ({connectors})")

    floor_g = next(f for f in package["floors"] if f["floor_code"] == "G")
    plan_path = os.path.join(out_dir, floor_g["plan"]["file"])
    check(os.path.isfile(plan_path), "plan image written")
    image = bpy.data.images.load(plan_path)
    width, height = image.size
    check((width, height) == (floor_g["plan"]["width_px"], floor_g["plan"]["height_px"]), f"plan size matches the package ({width}x{height})")
    pixels = list(image.pixels)
    mpp = floor_g["plan"]["metres_per_pixel"]
    ox, oy = floor_g["plan"]["world_origin_x_m"], floor_g["plan"]["world_origin_y_m"]

    def pixel(x, y):
        px = int((x - ox) / mpp)
        py = int((oy - y) / mpp)
        row = height - 1 - py
        i = (row * width + px) * 4
        return pixels[i : i + 4]

    corridor = pixel(*at(20.0, 17.0)[:2])
    pillar = pixel(*at(28.0, 19.0)[:2])
    outside = pixel(ox + 0.2, oy - 0.2)
    check(corridor[3] > 0.9 and corridor[0] > 0.8, f"corridor is light floor on the plan {corridor}")
    check(pillar[3] > 0.9 and pillar[0] < 0.4, f"pillar is dark on the plan {pillar}")
    check(outside[3] < 0.1, "outside the building is transparent")
    bpy.data.images.remove(image)

    model = os.path.join(out_dir, floor_g["model"]["file"])
    with open(model, "rb") as handle:
        check(handle.read(4) == b"glTF", "floor model is a GLB file")
    check(os.path.isfile(os.path.join(out_dir, floor_g["graph_preview_file"])), "graph preview written")
    check(os.path.isfile(os.path.join(out_dir, "report.json")), "report written")

    previous = os.getcwd()
    os.chdir(os.path.dirname(out_dir))
    try:
        ism_map.export_package("relative_export")
        check(os.path.isfile(os.path.join("relative_export", "floors", "G_plan.png")), "a relative --out folder gets its plan images too")
    finally:
        os.chdir(previous)
    return package


def main():
    argv = sys.argv[sys.argv.index("--") + 1 :] if "--" in sys.argv else []
    parser = argparse.ArgumentParser()
    parser.add_argument("--keep", help="write the work files here and keep them")
    parser.add_argument("--write-sample", action="store_true")
    args = parser.parse_args(argv)
    work = os.path.abspath(args.keep) if args.keep else tempfile.mkdtemp(prefix="ism_map_test_")
    if args.keep and os.path.isdir(work):
        shutil.rmtree(work)
    os.makedirs(work, exist_ok=True)
    try:
        bpy.ops.wm.read_factory_settings(use_empty=True)
        truth = ism_map.make_test_scan(os.path.join(work, "scans"))
        bpy.ops.wm.read_factory_settings(use_empty=True)
        at = Frame(truth)
        test_prepare(truth, at)
        test_draft_graph(at)
        place_markers(at)
        test_validation(at)
        package = test_export(os.path.join(work, "package"), at)
        bpy.ops.wm.save_as_mainfile(filepath=os.path.join(work, "mall.blend"))
        if args.write_sample:
            sample = os.path.join(ROOT, "tools", "blender", "samples", "pilot_map_package.json")
            os.makedirs(os.path.dirname(sample), exist_ok=True)
            with open(sample, "w", encoding="utf-8", newline="\n") as handle:
                json.dump(package, handle, indent=2)
                handle.write("\n")
            print(f"sample written to {sample}", flush=True)
    except Exception:
        traceback.print_exc()
        CHECKS["failed"].append("unexpected exception")
    finally:
        if not args.keep:
            shutil.rmtree(work, ignore_errors=True)
    print(f"\n{CHECKS['passed']} passed, {len(CHECKS['failed'])} failed", flush=True)
    for message in CHECKS["failed"]:
        print(f"  failed: {message}", flush=True)
    sys.exit(1 if CHECKS["failed"] else 0)


main()
