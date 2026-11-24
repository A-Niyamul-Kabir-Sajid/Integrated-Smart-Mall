# Scanning the mall with an iPhone 17 Pro

How to capture the LiDAR scans that the [Blender pipeline](README.md) turns into the indoor map. You need one mesh file per floor, at true scale and with the floor level. The scan is used for the floor plan, the navigation graph and the exact pillar faces where the QR codes go. The phone does not need to recognise the mall later: the QR codes do that.

## Before you go

- **Permission.** Ask mall management in writing. Scan when the mall is quiet (opening hour, or after closing). Moving people leave floating "ghost" fragments, and you must not record shoppers.
- **App.** Any LiDAR app that exports a textured mesh in metres works. Good choices: **Scaniverse**, **Polycam** or **3d Scanner App**. Check each app's current free export limits before the visit. Export **GLB** (best), OBJ or USDZ. A point cloud (PLY without faces) does not work.
- **Kit.** iPhone 17 Pro charged (scanning drains it fast) plus a power bank, a 5 m or longer tape measure, a notebook, and the mall's floor plan if you can get it.
- **Pillar names.** Agree a name for every pillar (P1, P2, ...) and face (north, east, south, west) with the team. QR codes and labels use these names (for example `G-C03` = ground floor, checkpoint 3, on pillar P3 south face).

## Scanning a floor

1. **Split the floor into sections** of about 15 to 20 m of corridor, each overlapping the next by 2 to 3 m. A pillar or doorway in the overlap makes lining them up easy. Long single scans drift, so short sections are more accurate.
2. **Hold the phone at chest height**, screen facing you, and walk slowly (about half a normal walking pace). Sweep up and down: the floor in front of you, the walls and pillar faces up to about 2.5 m. The ceiling does not matter; the pipeline cuts it off.
3. **Capture every pillar on all four faces.** Walk around each one. QR codes go on these faces, so they must be sharp in the scan.
4. **Look 2 to 3 m into every shop doorway.** The graph needs the doorway and a bit of floor inside. You do not need whole shop interiors.
5. **Lifts, escalators and stairs:** scan the landing on each floor, up to the lift doors or the first step.
6. **Problem surfaces:** glass shop fronts, mirrors, shiny black floors and direct sunlight scan badly. Scan them at an angle and from closer, and note them for a manual check later.
7. **Before you leave each section**, look through the preview for holes in the floor, missing walls or a bent corridor. Rescan it if needed; returning later costs more.
8. **Export** each section as GLB in metres, with the default orientation (Y up). "Medium" detail and 2k to 4k textures are enough. Name the files `<floor>_<section>_<date>.glb`, for example `G_s03_2026-11-28.glb`.

## Measurements to write down

- **Two check distances per floor** with the tape, for example the centre-to-centre distance between two pillars about 10 m apart. After `prepare`, measure the same distance in Blender in the top view with the Measure tool (ruler icon in the toolbar; drag from one pillar centre to the other). If they differ by more than 1%, run prepare again with `--scale <tape distance / Blender distance>`.
- **Floor-to-floor height** for each pair of floors (from the plan, the mall office, or step count times step height). This gives the `--elevation` values (for example ground 0, first floor 5.0).
- **QR mounting height** once the codes are up (centre of the code above the floor, usually 1.4 m), and the printed QR size (usually 0.18 m).

## Joining sections into one file per floor

The pipeline takes one mesh per floor. To join sections in Blender:

1. File > Import > glTF for every section of the floor.
2. Top view (numpad 7), X-ray on (Alt+Z). Keep the first section where it is. Move (G) and rotate around Z (R Z) each other section until the pillars and walls in the overlap sit exactly on top of each other. Zoom in on pillar corners for precision; only turn around Z and move sideways, never tilt.
3. Select all sections, Ctrl+J to join them, then File > Export > glTF 2.0 (`.glb`), "Selected Objects" only. Name it `<floor>.glb`.
4. Run `prepare` on that file (README step 2).

When a whole floor fits in one smooth scan (a small floor, or an app's large-space mode), you can skip this step.

## Lining floors up

`prepare` squares each floor to the axes and moves its lowest corner to (0, 0). Floors with the same outline line up by themselves. To check, look at the top view in X-ray with all floors visible: the pillars (which run through every floor) should sit on top of each other.

- Turned by 90 or 180 degrees: prepare again with `--replace --yaw-offset 90` (or 180, 270).
- Shifted: `--replace --offset X,Y` with the shift in metres (measure it between the same pillar on both floors).

## After scanning

- Keep raw scans, joined files and `.blend` files in `tools/blender/work/` (ignored by git). They show shop interiors; do not post them publicly.
- Mount the QR codes on the agreed pillar faces, then place the checkpoint markers in Blender on those faces at the measured height (README step 5).
- If the mall changes (new shop front, moved kiosk), rescan only that floor, prepare it with `--replace`, fix the graph and export a new map version.
