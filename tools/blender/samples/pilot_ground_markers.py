"""Demonstration markers for the first real scan (ground floor, Jira ISM-57).

The scan "Scaniverse 2026-11-22 151619.glb" is one L-shaped corridor, about
1.5 m wide and 16 m long, opening east at the north end into a room about
6 m deep. After `prepare` (floor G, elevation 0) and `draft-graph`, this
places QR checkpoints, shop doors and facilities so the map can be routed.

The places are DEMONSTRATION ONLY: they were chosen from the plan image, not
from the real building. The unit codes are those of the demo mall
(niagara-city-mall) so "Show on map" works in the Smart Mall app. Replace
them with the real QR positions, doors and unit codes before real use.

Coordinates are in the mall frame after `prepare`: metres, x east, y north,
z up; a QR code's yaw is the direction its face points (0 = east, 90 = north,
180 = west, 270 = south).

Run:
  blender -b tools/blender/work/real-ground/mall.blend --factory-startup \
      --python-exit-code 1 -P tools/blender/samples/pilot_ground_markers.py
"""

import os
import sys

import bpy

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), ".."))
import ism_map  # noqa: E402

MARKERS = [
    # QR codes on walls the scan really has (found by casting rays from the
    # corridor centre): the east wall near the south end (x 3.43 m at y 2 m)
    # and the north wall of the end room (y 16.32 m at x 4 to 5 m), 1.4 m up.
    ("checkpoint", (3.41, 2.0, 1.4), 180, {"ism_code": "G-C01", "ism_label": "South end, east wall (demo)"}),
    ("checkpoint", (4.5, 16.3, 1.4), 270, {"ism_code": "G-C02", "ism_label": "North room, north wall (demo)"}),
    # Shop doors of the demo mall's ground-floor units.
    ("shop_entrance", (3.1, 4.0, 0.0), 0, {"ism_unit_code": "G-01"}),
    ("shop_entrance", (2.9, 8.0, 0.0), 0, {"ism_unit_code": "G-02"}),
    ("shop_entrance", (1.3, 11.0, 0.0), 0, {"ism_unit_code": "G-03"}),
    ("shop_entrance", (6.0, 14.0, 0.0), 0, {"ism_unit_code": "G-04"}),
    # Facilities, with the Smart Mall app's facility types.
    ("facility", (1.3, 6.5, 0.0), 0, {"ism_facility_name": "Washroom G (demo)", "ism_facility_type": "toilet"}),
    ("facility", (5.0, 15.5, 0.0), 0, {"ism_facility_name": "Information desk (demo)", "ism_facility_type": "information"}),
    ("facility", (2.0, 0.0, 0.0), 0, {"ism_facility_name": "South entrance (demo)", "ism_facility_type": "exit"}),
]

# Remove markers left by an earlier run, so the script can be run again.
for obj in [o for o in bpy.data.objects if o.get("ism_kind")]:
    bpy.data.objects.remove(obj, do_unlink=True)

for kind, location, yaw, properties in MARKERS:
    ism_map.add_marker(kind, location, yaw, properties)
    print(f"[pilot] {kind} {properties} at {location}")

bpy.ops.wm.save_mainfile()
print(f"[pilot] {len(MARKERS)} markers placed and saved")
