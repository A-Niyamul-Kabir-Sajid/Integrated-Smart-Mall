// Run with: node --test tests/js/ar.test.mjs   (from map-service; Node 20+, no packages)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as M from '../../public/ar/ar-math.js';

const DEG = Math.PI / 180;
const close = (actual, expected, tolerance = 1e-9, what = '') => assert.ok(Math.abs(actual - expected) <= tolerance, `${what} ${actual} is not within ${tolerance} of ${expected}`);
const closeVec = (actual, expected, tolerance, what = '') => expected.forEach((value, i) => close(actual[i], value, tolerance, `${what}[${i}]`));
const angleBetween = (a, b) => Math.abs(M.wrapAngle(a - b));

// A phone camera: 60 degree vertical field of view, 3:4 picture, slightly off-centre like a real AR camera.
function projection(fovYDeg = 60, aspect = 0.75, near = 0.05, far = 100) {
    const f = 1 / Math.tan((fovYDeg * DEG) / 2);
    const p = new Float32Array(16);
    p[0] = f / aspect;
    p[5] = f;
    p[8] = 0.02;
    p[9] = -0.01;
    p[10] = (far + near) / (near - far);
    p[11] = -1;
    p[14] = (2 * far * near) / (near - far);
    return p;
}

// Column-major camera-to-world transform: turn by yaw about +y, then pitch about +x, at a position.
function cameraMatrix(yawDeg, pitchDeg, at) {
    const cy = Math.cos(yawDeg * DEG);
    const sy = Math.sin(yawDeg * DEG);
    const cp = Math.cos(pitchDeg * DEG);
    const sp = Math.sin(pitchDeg * DEG);
    // R = Ry(yaw) * Rx(pitch), columns are the camera's x, y and z axes in the world.
    const x = [cy, 0, -sy];
    const y = [sy * sp, cp, cy * sp];
    const z = [sy * cp, -sp, cy * cp];
    return Float64Array.from([...x, 0, ...y, 0, ...z, 0, ...at, 1]);
}

// Deterministic small noise in normalised device units.
function jitter(seed) {
    let state = seed;
    return () => {
        state = (state * 1103515245 + 12345) % 2147483648;
        return (state / 2147483648 - 0.5) * 0.003;
    };
}

test('headings and turns about +y agree in the WebXR frame', () => {
    for (const heading of [0, 0.5, 1.7, -2.4, Math.PI]) {
        close(M.wrapAngle(M.headingOf(M.rotateY([1, 0, 0], heading)) - heading), 0, 1e-12, 'heading');
    }
    // A mall yaw of 90 degrees (north, +y) is -z in three.js.
    closeVec(M.rotateY([1, 0, 0], 90 * DEG), [0, 0, -1], 1e-12, 'north');
    assert.equal(M.wrapAngle(-Math.PI), Math.PI);
});

test('aligning at a QR code recovers the frame and converts both ways', () => {
    const truth = { angle: 0.7, offset: [1.5, -1.2, 3] };
    const qr = { x: 5, y: 10.8, z: 6.4, yaw: 270 };
    const centre = M.toXr(truth, qr);
    const aligned = M.alignAt(centre, 270 * DEG + truth.angle, qr);

    close(aligned.angle, 0.7, 1e-12, 'angle');
    closeVec(aligned.offset, truth.offset, 1e-12, 'offset');
    for (const point of [{ x: 0, y: 0, z: 0 }, { x: -3.2, y: 7, z: 1.5 }, { x: 12, y: -4, z: 5 }]) {
        const back = M.toMall(aligned, M.toXr(aligned, point));
        closeVec([back.x, back.y, back.z], [point.x, point.y, point.z], 1e-9, 'round trip');
    }
    // A mall direction keeps its yaw plus the alignment angle.
    const a = M.toXr(aligned, { x: 0, y: 0, z: 0 });
    const b = M.toXr(aligned, { x: Math.cos(30 * DEG), y: Math.sin(30 * DEG), z: 0 });
    close(M.wrapAngle(M.headingOf([b[0] - a[0], 0, b[2] - a[2]]) - (30 * DEG + 0.7)), 0, 1e-12, 'direction');
});

test('holding the phone flat on the code faces the code the other way', () => {
    const truth = { angle: -1.1, offset: [0.4, 0.2, -2] };
    const qr = { x: -0.8, y: 0, z: 1.4, yaw: 0 };
    const centre = M.toXr(truth, qr);
    const facing = M.rotateY([1, 0, 0], 0 + truth.angle);
    // The camera looks into the wall, a little downwards.
    const forward = [-facing[0], -0.2, -facing[2]];
    const aligned = M.alignByTouch(centre, forward, qr);

    close(M.wrapAngle(aligned.angle - truth.angle), 0, 1e-12, 'angle');
    closeVec(aligned.offset, truth.offset, 1e-9, 'offset');
    assert.equal(M.alignByTouch(centre, [0.1, -0.95, 0], qr), null, 'a phone lying flat says nothing about the wall');
});

test('the floor can be put where the phone found it', () => {
    const aligned = M.onFloor({ angle: 0.3, offset: [1, 2, 3] }, -1.32, 5);
    assert.deepEqual(aligned.offset, [1, -6.32, 3]);
    close(M.toXr(aligned, { x: 2, y: 2, z: 5 })[1], -1.32, 1e-12, 'floor height');
});

test('several sightings average across the turn from -180 to 180 degrees', () => {
    const pose = M.averagePose([
        { centre: [0, 1, 0], heading: 179 * DEG },
        { centre: [0.02, 1, 0], heading: -179 * DEG },
    ]);
    close(Math.abs(pose.heading), Math.PI, 1e-9, 'heading');
    close(pose.spreadDeg, 1, 1e-9, 'spread');
    closeVec(pose.centre, [0.01, 1, 0], 1e-12, 'centre');
    close(pose.spreadM, 0.01, 1e-12, 'spread metres');
});

test('the pose of a QR code is found from its four corners in a picture', () => {
    const p = projection();
    const camera = cameraMatrix(30, -10, [1, 1.5, 2]);
    const view = M.invertRigid(camera);
    const eye = [1, 1.5, 2];
    const forward = [-camera[8], -camera[9], -camera[10]];
    const centre = [eye[0] + 1.1 * forward[0] + 0.12, eye[1] + 1.1 * forward[1] - 0.05, eye[2] + 1.1 * forward[2]];
    const towardsEye = M.headingOf([eye[0] - centre[0], 0, eye[2] - centre[2]]);
    const width = 0.18;
    const noise = jitter(7);

    for (const turnDeg of [0, 25, -35]) {
        const heading = towardsEye + turnDeg * DEG;
        const corners = M.codeCorners(centre, heading, width).map((corner) => M.projectToNdc(p, view, corner));
        const exact = M.estimateCodePose(corners, p, camera, width);
        closeVec(exact.centre, centre, 1e-5, `exact centre at ${turnDeg}`);
        close(angleBetween(exact.heading, heading), 0, 1e-5, `exact heading at ${turnDeg}`);
        close(exact.distance, Math.hypot(...centre.map((value, i) => value - eye[i])), 1e-5, 'distance');
        assert.ok(exact.error < 1e-6, 'an exact picture fits exactly');
        assert.ok(exact.ambiguity > 10, `an exact picture is not ambiguous (${exact.ambiguity})`);

        const noisy = corners.map(([x, y]) => [x + noise(), y + noise()]);
        const estimate = M.estimateCodePose(noisy, p, camera, width);
        assert.ok(Math.hypot(...estimate.centre.map((value, i) => value - centre[i])) < 0.02, `centre within 2 cm at ${turnDeg}`);
        assert.ok(angleBetween(estimate.heading, heading) < 2 * DEG, `heading within 2 degrees at ${turnDeg}: ${(angleBetween(estimate.heading, heading) / DEG).toFixed(2)}`);

        // A detector that starts the corners elsewhere gives the same pose.
        const turned = M.estimateCodePose([...corners.slice(1), corners[0]], p, camera, width);
        closeVec(turned.centre, centre, 1e-5, 'turned order');
        assert.equal(turned.turn, 3);
    }
});

test('a code printed at another size moves the estimate in proportion, and nonsense is refused', () => {
    const p = projection();
    const camera = cameraMatrix(0, 0, [0, 1.4, 0]);
    const view = M.invertRigid(camera);
    const centre = [0, 1.4, -1];
    // The code faces the camera (+z, heading -90 degrees).
    const corners = M.codeCorners(centre, -90 * DEG, 0.18).map((corner) => M.projectToNdc(p, view, corner));
    const right = M.estimateCodePose(corners, p, camera, 0.18);
    close(right.distance, 1, 1e-4, 'distance');
    close(angleBetween(right.heading, -90 * DEG), 0, 1e-4, 'facing the camera');
    const wrong = M.estimateCodePose(corners, p, camera, 0.24);
    close(wrong.distance / right.distance, 0.24 / 0.18, 0.01, 'size ratio');

    assert.equal(M.estimateCodePose([[0, 0], [0, 0], [0, 0], [0, 0]], p, camera, 0.18), null, 'a point is not a code');
    assert.equal(M.estimateCodePose([[0, 0], [0.1, 0]], p, camera, 0.18), null, 'two corners are not enough');
    assert.equal(M.estimateCodePose(corners, p, camera, 0), null, 'a code needs a size');
    closeVec(M.pixelToNdc(0, 0, 960, 540), [-1, 1], 1e-12, 'top-left pixel');
    closeVec(M.pixelToNdc(480, 270, 960, 540), [0, 0], 1e-12, 'centre pixel');
});

test('a scanned code gives its slug', () => {
    assert.equal(M.slugFromCode('http://127.0.0.1:8000/malls/niagara-city-mall/map/qr/k3j9x2m1q8wa'), 'k3j9x2m1q8wa');
    assert.equal(M.slugFromCode('https://mall.example/malls/x/map/qr/K3J9X2M1Q8WA/'), 'k3j9x2m1q8wa');
    assert.equal(M.slugFromCode('  k3j9x2m1q8wa '), 'k3j9x2m1q8wa');
    assert.equal(M.slugFromCode('https://example.com/qr/not a slug!'), null);
    assert.equal(M.slugFromCode(42), null);
});

// Ground floor: east 10 m, north 5 m, the lift up 5 m, then north 3 m and west 6 m on the first floor.
const route = {
    path: [
        { node_code: 'G-C01', floor_code: 'G', position: { x: 0, y: 0, z: 0 } },
        { node_code: 'G-N0001', floor_code: 'G', position: { x: 10, y: 0, z: 0 } },
        { node_code: 'G-X-Lift', floor_code: 'G', position: { x: 10, y: 5, z: 0 } },
        { node_code: '1-X-Lift', floor_code: '1', position: { x: 10, y: 5, z: 5 } },
        { node_code: '1-N0001', floor_code: '1', position: { x: 10, y: 8, z: 5 } },
        { node_code: 'E-1-105', floor_code: '1', position: { x: 4, y: 8, z: 5 } },
    ],
    steps: [
        { type: 'start', instruction: 'Start at Main entrance.', node_code: 'G-C01' },
        { type: 'walk', instruction: 'Walk 10 m.', node_code: 'G-C01' },
        { type: 'walk', instruction: 'Turn left and walk 5 m.', node_code: 'G-N0001' },
        { type: 'connector', instruction: 'Take Central Lift up to First floor.', node_code: 'G-X-Lift', to_floor_code: '1' },
        { type: 'walk', instruction: 'Walk 3 m.', node_code: '1-X-Lift' },
        { type: 'walk', instruction: 'Turn left and walk 6 m.', node_code: '1-N0001' },
        { type: 'arrive', instruction: 'Arrive at Shop 1-105.', node_code: 'E-1-105' },
    ],
};

test('the route is measured and split by floor', () => {
    const lengths = M.pathLengths(route.path);
    assert.deepEqual(lengths, [0, 10, 15, 20, 23, 29]);
    assert.deepEqual(M.routeRuns(route.path), [{ floor: 'G', from: 0, to: 2 }, { floor: '1', from: 3, to: 5 }]);
    assert.deepEqual(M.pointAt(route.path, lengths, 12.5), { x: 10, y: 2.5, z: 0, floor_code: 'G' });
    assert.deepEqual(M.pointAt(route.path, lengths, 99), { x: 4, y: 8, z: 5, floor_code: '1' });
});

test('the visitor is found on the route of their floor', () => {
    const lengths = M.pathLengths(route.path);
    const ground = M.locate(route.path, lengths, 'G', { x: 5, y: 1 });
    close(ground.distance, 1, 1e-12, 'ground distance');
    close(ground.along, 5, 1e-12, 'ground along');
    const first = M.locate(route.path, lengths, '1', { x: 7, y: 9 });
    close(first.distance, 1, 1e-12, 'first-floor distance');
    close(first.along, 26, 1e-12, 'first-floor along');
    assert.equal(M.locate(route.path, lengths, 'B1', { x: 0, y: 0 }), null);
});

test('the next step and its distance follow the visitor along the route', () => {
    const starts = M.stepStarts(route, M.pathLengths(route.path));
    assert.deepEqual(starts.map((step) => step.at), [0, 0, 10, 15, 20, 23, 29]);

    const next = (along) => {
        const found = M.nextStep(starts, along);
        return [found.step.instruction, Math.round(found.distance * 10) / 10];
    };
    assert.deepEqual(next(0), ['Walk 10 m.', 0]);
    assert.deepEqual(next(5), ['Turn left and walk 5 m.', 5]);
    assert.deepEqual(next(10.5), ['Turn left and walk 5 m.', 0]);
    assert.deepEqual(next(12), ['Take Central Lift up to First floor.', 3]);
    assert.deepEqual(next(24.5), ['Arrive at Shop 1-105.', 4.5]);
    assert.deepEqual(next(29), ['Arrive at Shop 1-105.', 0]);
});

test('floor arrows are spaced along the route with the heading of their piece', () => {
    const arrows = M.placeAlong(route.path.slice(0, 3), 2, 1);
    assert.deepEqual(arrows.map((arrow) => [arrow.x, arrow.y]), [[1, 0], [3, 0], [5, 0], [7, 0], [9, 0], [10, 1], [10, 3], [10, 5]]);
    close(arrows[0].heading, 0, 1e-12, 'east');
    close(arrows[5].heading, Math.PI / 2, 1e-12, 'north');
});

test('steps get arrows, bearings say which way to turn, and the nearest node is found', () => {
    assert.deepEqual(route.steps.map(M.stepIcon), ['ahead', 'ahead', 'left', 'up', 'ahead', 'left', 'arrive']);
    assert.equal(M.stepIcon({ type: 'connector', instruction: 'Take East Escalator Down to Ground floor.' }), 'down');
    assert.equal(M.stepIcon({ type: 'walk', instruction: 'Bear right and walk 4 m.' }), 'bear-right');
    assert.equal(M.stepIcon({ type: 'walk', instruction: 'Turn around and walk 2 m.' }), 'back');

    close(M.bearingTo(0, [0, 0, 0], [0, 0, -1]), 90, 1e-9, 'north is to the left of east');
    close(M.bearingTo(0, [0, 0, 0], [0, 0, 1]), -90, 1e-9, 'south is to the right');
    close(Math.abs(M.bearingTo(0, [0, 0, 0], [-1, 0, 0])), 180, 1e-9, 'west is behind');

    const nodes = [{ code: 'G-N1', floor: 'G', x: 0, y: 0 }, { code: 'G-N2', floor: 'G', x: 4, y: 0 }, { code: '1-N1', floor: '1', x: 3, y: 0 }];
    assert.deepEqual(M.nearestNode(nodes, 'G', { x: 3, y: 0.5 }).code, 'G-N2');
    assert.equal(M.nearestNode(nodes, 'B1', { x: 0, y: 0 }), null);
});
