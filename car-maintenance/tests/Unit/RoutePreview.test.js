import test from 'node:test';
import assert from 'node:assert/strict';
import { previewPoints, formatTime } from '../../resources/js/lib/routePreview.js';

test('course previews preserve bends and fit horizontal and vertical routes within padding', () => {
    for (const route of [
        [
            { latitude: 0, longitude: 0 },
            { latitude: 0, longitude: 1 },
        ],
        [
            { latitude: 0, longitude: 0 },
            { latitude: 1, longitude: 0 },
        ],
        [
            { latitude: 0, longitude: 0 },
            { latitude: 0, longitude: 1 },
            { latitude: 1, longitude: 1 },
        ],
    ]) {
        const points = previewPoints(route);
        assert.equal(points.length, route.length);
        assert.ok(
            points.every(
                ([x, y]) =>
                    Number.isFinite(x) && Number.isFinite(y) && x >= 35.99 && x <= 444.01 && y >= 35.99 && y <= 204.01
            )
        );
    }
    const bend = previewPoints([
        { latitude: 0, longitude: 0 },
        { latitude: 0, longitude: 1 },
        { latitude: 1, longitude: 1 },
    ]);
    assert.equal(bend[0][1], bend[1][1]);
    assert.equal(bend[1][0], bend[2][0]);
    assert.ok(bend[2][1] < bend[1][1]);
});

test('empty and coincident geometry stays finite', () => {
    assert.deepEqual(previewPoints([]), []);
    assert.deepEqual(previewPoints([{ latitude: 0, longitude: 0 }]), []);
    assert.deepEqual(
        previewPoints([
            { latitude: 0, longitude: 0 },
            { latitude: 0, longitude: 0 },
        ]),
        [
            [240, 120],
            [240, 120],
        ]
    );
});

test('times display milliseconds and hours without rounding away ranking differences', () => {
    assert.equal(formatTime(null), 'No time yet');
    assert.equal(formatTime(0), '00:00:00.000');
    assert.equal(formatTime(3661005), '01:01:01.005');
});
