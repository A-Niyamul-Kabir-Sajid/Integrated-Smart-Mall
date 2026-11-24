// Run with: node --test tests/js/   (from map-service; Node 20+, no packages)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as A from '../../public/viewer/annotations.js';

const close = (actual, expected, tolerance = 1e-9) => assert.ok(Math.abs(actual - expected) <= tolerance, `${actual} is not close to ${expected}`);

test('mall and three.js coordinates convert both ways (three = x, z, -y)', () => {
    assert.deepEqual(A.mallToThree({ x: 1, y: 2, z: 3 }), [1, 3, -2]);
    assert.deepEqual(A.threeToMall([1, 3, -2]), { x: 1, y: 2, z: 3 });
    assert.deepEqual(A.threeToMall({ x: 4, y: 0.5, z: 6 }), { x: 4, y: -6, z: 0.5 });
});

test('a panel facing yaw points its +Z normal that way in three.js', () => {
    for (const yaw of [0, 45, 90, 180, 270, 333]) {
        const rotationY = A.yawToRotationY(yaw);
        const normal = [Math.sin(rotationY), 0, Math.cos(rotationY)];
        close(A.threeDirectionToYaw(normal), yaw, 1e-6);
    }
    close(A.threeDirectionToYaw([0, 0, -1]), 90);
    assert.equal(A.normaliseDegrees(-1e-15), 0);
    assert.equal(A.roundYaw(359.6), 0);
    assert.equal(A.roundYaw(-90), 270);
    close(A.threeDirectionToYaw([-1, 0, 0]), 180);
});

test('surfaces are walls when their normal is mostly horizontal', () => {
    assert.equal(A.mountForNormal([1, 0, 0]), 'wall');
    assert.equal(A.mountForNormal([0.7, 0.5, 0.5]), 'wall');
    assert.equal(A.mountForNormal([0, 1, 0]), 'floor');
    assert.equal(A.mountForNormal({ x: 0.2, y: 0.95, z: 0 }), 'floor');
});

test('new annotations get defaults per kind and mount, and the next free id', () => {
    const existing = [{ id: 'ann-0007' }, { id: 'other' }];
    const sign = A.createAnnotation('shop_sign', { x: 1.23456, y: 2, z: 1.5 }, { yaw_deg: -90, mount: 'wall', floor_code: 'G', existing, now: '2026-11-22T00:00:00Z' });
    assert.equal(sign.id, 'ann-0008');
    assert.equal(sign.width_m, 2.4);
    assert.equal(sign.lift_m, 0);
    assert.equal(sign.yaw_deg, 270);
    assert.equal(sign.position.x, 1.2346);
    assert.deepEqual(A.validateAnnotation(sign), []);
    close(A.panelHeight(sign), 0.6);

    const ad = A.createAnnotation('advertisement', { x: 0, y: 0, z: 0 }, { mount: 'floor' });
    assert.equal(ad.lift_m, 0.1);
    assert.equal(ad.subtitle, 'Your offer here');
    close(A.panelHeight(ad), 1.2);
    assert.equal(A.defaultLift('shop_sign', 'floor'), 2.2);
    assert.throws(() => A.createAnnotation('banner', { x: 0, y: 0, z: 0 }));
});

test('validation reports every broken field', () => {
    const bad = { ...A.createAnnotation('shop_sign', { x: 0, y: 0, z: 0 }), text: '', background: 'red', width_m: 50, yaw_deg: 360, mount: 'ceiling', lift_m: 20, unit_code: 'x'.repeat(41) };
    const errors = A.validateAnnotation(bad).join(' | ');
    for (const part of ['text', 'colours', 'width', 'yaw', 'mount', 'height', 'unit code']) {
        assert.match(errors, new RegExp(part));
    }
    assert.deepEqual(A.validateAnnotation(null), ['not an object']);
});

test('images are data URLs under 1 MB or links from the map service', () => {
    assert.ok(A.isImageReference('data:image/png;base64,AAAA'));
    assert.ok(A.isImageReference('http://127.0.0.1:8100/files/maps/x/images/a.jpg?signature=1'));
    assert.ok(A.isImageReference('/files/maps/x/images/a.jpg'));
    assert.ok(!A.isImageReference('data:image/gif;base64,AAAA'));
    assert.ok(!A.isImageReference('javascript:alert(1)'));
    assert.ok(!A.isImageReference(`data:image/png;base64,${'A'.repeat(1_600_000)}`));
});

test('serialise and parse round-trip; invalid entries are skipped and duplicate ids renumbered', () => {
    const a = A.createAnnotation('shop_sign', { x: 1, y: 2, z: 3 });
    const b = { ...A.createAnnotation('advertisement', { x: 4, y: 5, z: 6 }), id: a.id };
    const broken = { ...a, id: 'ann-0099', text: '' };
    const document = A.serialise([a, b, broken], { generated_at: '2026-11-22T09:29:52+00:00' });
    assert.equal(document.format, 'ism-map-annotations');
    assert.equal(document.package_generated_at, '2026-11-22T09:29:52+00:00');

    const result = A.parse(JSON.stringify(document));
    assert.equal(result.annotations.length, 2);
    assert.equal(result.annotations[0].id, 'ann-0001');
    assert.equal(result.annotations[1].id, 'ann-0002');
    assert.equal(result.errors.length, 1);
    assert.deepEqual(A.parse({ format: 'other' }).errors, ['not an ism-map-annotations file']);
    assert.deepEqual(A.parse({ format: 'ism-map-annotations', format_version: 9 }).errors, ['unsupported format_version 9']);
});

test('text wraps to the allowed number of lines', () => {
    const measure = (text) => text.length * 10;
    assert.deepEqual(A.wrapText('Cafe Aroma', 200, measure), ['Cafe Aroma']);
    assert.deepEqual(A.wrapText('Winter Sale fifty percent off', 120, measure), ['Winter Sale', 'fifty…']);
    assert.deepEqual(A.wrapText('Supercalifragilistic', 50, measure), ['Supercalifragilistic']);
});
