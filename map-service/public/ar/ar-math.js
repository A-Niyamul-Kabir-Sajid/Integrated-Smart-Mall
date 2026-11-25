/**
 * Maths of the AR directions page (ISM-65): the mall frame and the WebXR
 * frame, lining the two up at a QR code, the pose of a QR code the camera
 * sees, and where the visitor is along the route. No DOM and no three.js,
 * so it runs under node --test.
 *
 * Frames: the mall frame ism-mall-local-v1 (metres, x and y horizontal, z up,
 * yaw counterclockwise from +x) and the WebXR / three.js frame (y up), with
 * three = (x, z, -y). A heading in the WebXR frame is an angle about +y
 * measured the same way, so heading h points along (cos h, 0, -sin h) and a
 * mall yaw is the same angle in both frames.
 */
import { mallToThree, threeToMall } from '../viewer/annotations.js';

export const DEG = Math.PI / 180;

const vec = (value) => (Array.isArray(value) || ArrayBuffer.isView(value) ? [value[0], value[1], value[2]] : [value.x, value.y, value.z]);
const sub = (a, b) => [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
const add = (a, b) => [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
const scale = (a, s) => [a[0] * s, a[1] * s, a[2] * s];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
const length = (a) => Math.hypot(a[0], a[1], a[2]);
const normalise = (a) => scale(a, 1 / (length(a) || 1));

/** An angle in radians wrapped to (-pi, pi]. */
export function wrapAngle(angle) {
    const wrapped = Math.atan2(Math.sin(angle), Math.cos(angle));
    return wrapped === -Math.PI ? Math.PI : wrapped;
}

/** Heading (radians) of a WebXR direction laid flat on the floor. */
export function headingOf(direction) {
    const [x, , z] = vec(direction);
    return Math.atan2(-z, x);
}

/** A WebXR vector turned by an angle (radians) about +y. */
export function rotateY(value, angle) {
    const [x, y, z] = vec(value);
    const c = Math.cos(angle);
    const s = Math.sin(angle);
    return [x * c + z * s, y, -x * s + z * c];
}

// ---- lining up the two frames ---------------------------------------------

/**
 * The alignment that puts a QR code of the map where the phone found it:
 * webxr = rotateY(three(mall), angle) + offset.
 *
 * @param {number[]} codeCentre  where the code's centre is, in WebXR metres
 * @param {number} codeHeading  WebXR heading (radians) the code's face points to
 * @param {{x: number, y: number, z: number, yaw: number}} qr  the code in the map (yaw in degrees)
 */
export function alignAt(codeCentre, codeHeading, qr) {
    const angle = wrapAngle(codeHeading - qr.yaw * DEG);
    const turned = rotateY(mallToThree(qr), angle);
    return { angle, offset: sub(vec(codeCentre), turned) };
}

/**
 * The alignment when the phone is held flat against the code, camera to the
 * wall: the code is where the camera is and faces the other way.
 * Null when the phone is not upright enough to tell which way the wall faces.
 */
export function alignByTouch(cameraPosition, cameraForward, qr) {
    const forward = normalise(vec(cameraForward));
    if (Math.abs(forward[1]) > 0.7) {
        return null;
    }
    return alignAt(cameraPosition, headingOf(forward) + Math.PI, qr);
}

/** A mall point {x, y, z} in WebXR coordinates. */
export function toXr(alignment, point) {
    return add(rotateY(mallToThree(point), alignment.angle), alignment.offset);
}

/** A WebXR point in mall coordinates {x, y, z}. */
export function toMall(alignment, position) {
    return threeToMall(rotateY(sub(vec(position), alignment.offset), -alignment.angle));
}

/** The same alignment with the floor at elevation `elevation` put at WebXR height `floorY`. */
export function onFloor(alignment, floorY, elevation) {
    return { ...alignment, offset: [alignment.offset[0], floorY - elevation, alignment.offset[2]] };
}

/**
 * One pose from several sightings of the same code: the mean centre, the
 * circular mean heading, and how far the sightings spread around them.
 *
 * @param {{centre: number[], heading: number}[]} poses
 */
export function averagePose(poses) {
    const centre = scale(poses.reduce((sum, pose) => add(sum, pose.centre), [0, 0, 0]), 1 / poses.length);
    const heading = Math.atan2(poses.reduce((sum, pose) => sum + Math.sin(pose.heading), 0), poses.reduce((sum, pose) => sum + Math.cos(pose.heading), 0));
    return {
        centre,
        heading,
        spreadDeg: Math.max(...poses.map((pose) => Math.abs(wrapAngle(pose.heading - heading)) / DEG)),
        spreadM: Math.max(...poses.map((pose) => length(sub(pose.centre, centre)))),
    };
}

// ---- the pose of a QR code in a camera picture ----------------------------

/** Normalised device coordinates (x right, y up, -1 to 1) of a picture pixel (y down). */
export function pixelToNdc(x, y, width, height) {
    return [(2 * x) / width - 1, 1 - (2 * y) / height];
}

function transformPoint(m, p) {
    return [m[0] * p[0] + m[4] * p[1] + m[8] * p[2] + m[12], m[1] * p[0] + m[5] * p[1] + m[9] * p[2] + m[13], m[2] * p[0] + m[6] * p[1] + m[10] * p[2] + m[14]];
}

function transformDirection(m, d) {
    return [m[0] * d[0] + m[4] * d[1] + m[8] * d[2], m[1] * d[0] + m[5] * d[1] + m[9] * d[2], m[2] * d[0] + m[6] * d[1] + m[10] * d[2]];
}

/** The inverse of a rigid column-major 4x4 transform. */
export function invertRigid(m) {
    const t = [m[12], m[13], m[14]];
    const r = [m[0], m[4], m[8], 0, m[1], m[5], m[9], 0, m[2], m[6], m[10], 0, 0, 0, 0, 1];
    const moved = transformDirection(r, t);
    r[12] = -moved[0];
    r[13] = -moved[1];
    r[14] = -moved[2];
    return r;
}

/** Where a world point appears in the view, in normalised device coordinates; null when behind the camera. */
export function projectToNdc(projection, worldToCamera, point) {
    const c = transformPoint(worldToCamera, point);
    const w = -c[2];
    if (w <= 1e-6) {
        return null;
    }
    return [(projection[0] * c[0] + projection[8] * c[2]) / w, (projection[5] * c[1] + projection[9] * c[2]) / w];
}

/** The world direction of the ray through a point of the view. */
export function viewRay(projection, cameraToWorld, ndc) {
    return normalise(transformDirection(cameraToWorld, [(ndc[0] + projection[8]) / projection[0], (ndc[1] + projection[9]) / projection[5], -1]));
}

/** Corners (top-left, top-right, bottom-right, bottom-left) of an upright code centred at c facing heading h. */
export function codeCorners(centre, heading, width) {
    const half = width / 2;
    const right = [-Math.sin(heading), 0, -Math.cos(heading)];
    return [[-half, half], [half, half], [half, -half], [-half, -half]].map(([across, up]) => [centre[0] + across * right[0], centre[1] + up, centre[2] + across * right[2]]);
}

function solve4(a, b) {
    const m = a.map((row, i) => [...row, b[i]]);
    for (let col = 0; col < 4; col++) {
        let pivot = col;
        for (let row = col + 1; row < 4; row++) {
            if (Math.abs(m[row][col]) > Math.abs(m[pivot][col])) pivot = row;
        }
        if (Math.abs(m[pivot][col]) < 1e-15) return null;
        [m[col], m[pivot]] = [m[pivot], m[col]];
        for (let row = 0; row < 4; row++) {
            if (row === col) continue;
            const factor = m[row][col] / m[col][col];
            for (let k = col; k < 5; k++) m[row][k] -= factor * m[col][k];
        }
    }
    return m.map((row, i) => row[4] / m[i][i]);
}

/**
 * The pose of a square QR code of known width seen in one camera picture,
 * for a code that hangs upright (top edge level, as on a wall or pillar):
 * its centre and the heading its face points to, in the frame of the camera
 * matrix. The corners come in the code's own order (top-left, top-right,
 * bottom-right, bottom-left, as a barcode detector gives them); a turned
 * order is tried too. A small least-squares fit over the centre and heading,
 * started facing the camera and 35 degrees to either side.
 *
 * Seen straight on, a small code says little about which way it faces: the
 * fit then has a second answer turned the other way. `ambiguity` is the
 * error of the best answer that faces at least 8 degrees differently,
 * divided by the error of the best one (Infinity when there is none); near 1
 * means the picture cannot tell them apart.
 *
 * @param {number[][]} corners  four [x, y] points in normalised device coordinates
 * @param {ArrayLike<number>} projection  the view's column-major projection matrix
 * @param {ArrayLike<number>} cameraToWorld  the view's column-major camera transform
 * @param {number} width  the printed code's width in metres
 * @returns {{centre: number[], heading: number, error: number, distance: number, turn: number, ambiguity: number}|null}
 *   error is the root-mean-square corner error in normalised device units
 */
export function estimateCodePose(corners, projection, cameraToWorld, width) {
    if (!Array.isArray(corners) || corners.length !== 4 || !(width > 0)) {
        return null;
    }
    const worldToCamera = invertRigid(cameraToWorld);
    const eye = [cameraToWorld[12], cameraToWorld[13], cameraToWorld[14]];
    const rays = corners.map((corner) => viewRay(projection, cameraToWorld, corner));
    const edgeAngles = [0, 1, 2, 3].map((i) => Math.acos(Math.min(1, Math.max(-1, dot(rays[i], rays[(i + 1) % 4])))));
    const meanAngle = edgeAngles.reduce((sum, angle) => sum + angle, 0) / 4;
    if (!(meanAngle > 1e-4)) {
        return null;
    }
    const startDistance = width / (2 * Math.tan(meanAngle / 2));
    const startCentre = add(eye, scale(normalise(rays.reduce(add, [0, 0, 0])), startDistance));
    const startHeading = headingOf(sub(eye, startCentre));

    const fits = [];
    for (let turn = 0; turn < 4; turn++) {
        const observed = [0, 1, 2, 3].map((i) => corners[(i + turn) % 4]);
        for (const side of [0, 35 * DEG, -35 * DEG]) {
            const fit = fitPose(observed, projection, worldToCamera, width, [...startCentre, startHeading + side]);
            if (fit && facesEye(fit, eye)) {
                fits.push({ ...fit, heading: wrapAngle(fit.heading), turn });
            }
        }
    }
    if (fits.length === 0) {
        return null;
    }
    fits.sort((a, b) => a.error - b.error);
    const best = fits[0];
    const other = fits.find((fit) => fit.turn === best.turn && Math.abs(wrapAngle(fit.heading - best.heading)) > 8 * DEG);
    return {
        ...best,
        distance: length(sub(eye, best.centre)),
        ambiguity: other === undefined ? Infinity : other.error / Math.max(best.error, 1e-12),
    };
}

function facesEye(fit, eye) {
    const toEye = sub(eye, fit.centre);
    return Math.cos(fit.heading) * toEye[0] - Math.sin(fit.heading) * toEye[2] > 0;
}

function fitPose(observed, projection, worldToCamera, width, start) {
    const residuals = (params) => {
        const out = [];
        for (const [i, corner] of codeCorners(params, params[3], width).entries()) {
            const seen = projectToNdc(projection, worldToCamera, corner);
            if (seen === null) return null;
            out.push(seen[0] - observed[i][0], seen[1] - observed[i][1]);
        }
        return out;
    };
    const cost = (r) => r.reduce((sum, value) => sum + value * value, 0);

    let params = start;
    let r = residuals(params);
    if (r === null) return null;
    let current = cost(r);
    let damping = 1e-3;
    for (let iteration = 0; iteration < 40; iteration++) {
        const jacobian = [0, 1, 2, 3].map((k) => {
            const step = 1e-5;
            const plus = [...params];
            const minus = [...params];
            plus[k] += step;
            minus[k] -= step;
            const rp = residuals(plus);
            const rm = residuals(minus);
            return rp === null || rm === null ? null : rp.map((value, i) => (value - rm[i]) / (2 * step));
        });
        if (jacobian.includes(null)) return null;
        const normal = [0, 1, 2, 3].map((a) => [0, 1, 2, 3].map((b) => jacobian[a].reduce((sum, value, i) => sum + value * jacobian[b][i], 0)));
        const gradient = [0, 1, 2, 3].map((a) => -jacobian[a].reduce((sum, value, i) => sum + value * r[i], 0));
        let improved = false;
        for (let attempt = 0; attempt < 8 && !improved; attempt++) {
            const damped = normal.map((row, a) => row.map((value, b) => (a === b ? value * (1 + damping) + 1e-12 : value)));
            const delta = solve4(damped, gradient);
            if (delta === null) break;
            const next = params.map((value, k) => value + delta[k]);
            const nr = residuals(next);
            if (nr !== null && cost(nr) < current) {
                const gain = current - cost(nr);
                params = next;
                r = nr;
                current = cost(nr);
                damping = Math.max(1e-9, damping / 4);
                improved = true;
                if (gain < 1e-18 || Math.hypot(...delta) < 1e-9) {
                    return { centre: params.slice(0, 3), heading: params[3], error: Math.sqrt(current / r.length) };
                }
            } else {
                damping *= 8;
            }
        }
        if (!improved) break;
    }
    return { centre: params.slice(0, 3), heading: params[3], error: Math.sqrt(current / r.length) };
}

/** The slug of a scanned QR code: the last part of its address (…/map/qr/<slug>), or the text itself. */
export function slugFromCode(text) {
    if (typeof text !== 'string') {
        return null;
    }
    let path = text.trim();
    try {
        path = new URL(path).pathname;
    } catch {
        // not an address: the text itself may be the slug
    }
    const parts = path.split('/').filter(Boolean);
    const last = (parts[parts.length - 1] || '').toLowerCase();
    return /^[a-z0-9]{1,24}$/.test(last) ? last : null;
}

// ---- the route --------------------------------------------------------------

const position = (point) => point.position ?? point;

/** Route length (metres) at each path point. */
export function pathLengths(path) {
    const lengths = [0];
    for (let i = 1; i < path.length; i++) {
        const a = position(path[i - 1]);
        const b = position(path[i]);
        lengths.push(lengths[i - 1] + Math.hypot(b.x - a.x, b.y - a.y, b.z - a.z));
    }
    return lengths;
}

/** Stretches of the route on one floor: consecutive path points, as {floor, from, to} path indices. */
export function routeRuns(path) {
    const runs = [];
    path.forEach((point, index) => {
        const last = runs[runs.length - 1];
        if (last && last.floor === point.floor_code) last.to = index;
        else runs.push({ floor: point.floor_code, from: index, to: index });
    });
    return runs;
}

/**
 * The closest point of the route on a floor to a mall point (x, y): how far
 * away it is (across the floor) and the route length there. Null when the
 * route has no stretch on that floor.
 */
export function locate(path, lengths, floor, point) {
    let best = null;
    for (const run of routeRuns(path)) {
        if (run.floor !== floor) continue;
        const consider = (index, t, x, y) => {
            const distance = Math.hypot(point.x - x, point.y - y);
            if (best === null || distance < best.distance - 1e-9) {
                best = { distance, along: lengths[index] + t * ((lengths[index + 1] ?? lengths[index]) - lengths[index]), index, t, run };
            }
        };
        if (run.from === run.to) {
            const p = position(path[run.from]);
            consider(run.from, 0, p.x, p.y);
            continue;
        }
        for (let i = run.from; i < run.to; i++) {
            const a = position(path[i]);
            const b = position(path[i + 1]);
            const dx = b.x - a.x;
            const dy = b.y - a.y;
            const squared = dx * dx + dy * dy;
            const t = squared < 1e-12 ? 0 : Math.min(1, Math.max(0, ((point.x - a.x) * dx + (point.y - a.y) * dy) / squared));
            consider(i, t, a.x + t * dx, a.y + t * dy);
        }
    }
    return best;
}

/** The mall point at a route length (clamped to the route). */
export function pointAt(path, lengths, along) {
    const total = lengths[lengths.length - 1];
    const s = Math.min(total, Math.max(0, along));
    let i = 0;
    while (i < lengths.length - 2 && lengths[i + 1] < s) i++;
    const a = position(path[i]);
    const b = position(path[Math.min(i + 1, path.length - 1)]);
    const span = (lengths[i + 1] ?? lengths[i]) - lengths[i];
    const t = span > 1e-9 ? (s - lengths[i]) / span : 0;
    return { x: a.x + t * (b.x - a.x), y: a.y + t * (b.y - a.y), z: a.z + t * (b.z - a.z), floor_code: path[t < 1 ? i : Math.min(i + 1, path.length - 1)].floor_code };
}

/** Each route step with the route length where it starts. */
export function stepStarts(route, lengths) {
    let from = 0;
    return route.steps.map((step, number) => {
        let index = number === 0 ? 0 : route.path.findIndex((point, i) => i >= from && point.node_code === step.node_code);
        if (step.type === 'arrive' && number === route.steps.length - 1) index = route.path.length - 1;
        if (index < 0) index = from;
        from = index;
        return { ...step, index, at: lengths[index] };
    });
}

/**
 * The step to do next and how far ahead it starts. A step stays "next"
 * until the visitor is a metre past its start.
 */
export function nextStep(starts, along) {
    for (let k = 1; k < starts.length; k++) {
        if (starts[k].at - along > -1) {
            return { step: starts[k], number: k, distance: Math.max(0, starts[k].at - along) };
        }
    }
    const last = starts[starts.length - 1];
    return { step: last, number: starts.length - 1, distance: 0 };
}

/** Points every `spacing` metres along a run of the path, each with the heading (radians) of its piece. */
export function placeAlong(points, spacing, startAt = spacing / 2) {
    const placed = [];
    let carried = startAt;
    for (let i = 0; i + 1 < points.length; i++) {
        const a = position(points[i]);
        const b = position(points[i + 1]);
        const piece = Math.hypot(b.x - a.x, b.y - a.y);
        if (piece < 1e-6) continue;
        const heading = Math.atan2(b.y - a.y, b.x - a.x);
        let s = carried;
        while (s <= piece + 1e-9) {
            const t = s / piece;
            placed.push({ x: a.x + t * (b.x - a.x), y: a.y + t * (b.y - a.y), z: a.z + t * (b.z - a.z), heading });
            s += spacing;
        }
        carried = s - piece;
    }
    return placed;
}

/** The kind of arrow for a step: ahead, left, right, bear-left, bear-right, back, up, down or arrive. */
export function stepIcon(step) {
    if (!step) return 'ahead';
    if (step.type === 'arrive' || step.type === 'stop') return 'arrive';
    const words = String(step.instruction || '');
    if (step.type === 'connector') return /\bdown\b/i.test(words) ? 'down' : 'up';
    if (/^Turn around/i.test(words)) return 'back';
    if (/^Turn left/i.test(words)) return 'left';
    if (/^Turn right/i.test(words)) return 'right';
    if (/^Bear left/i.test(words)) return 'bear-left';
    if (/^Bear right/i.test(words)) return 'bear-right';
    return 'ahead';
}

/**
 * Which way to turn to face a point: degrees in (-180, 180], positive to the
 * left (counterclockwise), from a WebXR heading at one point to another point.
 */
export function bearingTo(heading, from, to) {
    const a = vec(from);
    const b = vec(to);
    return wrapAngle(headingOf([b[0] - a[0], 0, b[2] - a[2]]) - heading) / DEG;
}

/** The node of a floor nearest to a mall point, for a new route from where the visitor stands. */
export function nearestNode(nodes, floor, point) {
    let best = null;
    for (const node of nodes) {
        if (node.floor !== floor) continue;
        const distance = Math.hypot(node.x - point.x, node.y - point.y);
        if (best === null || distance < best.distance) best = { code: node.code, distance };
    }
    return best;
}
