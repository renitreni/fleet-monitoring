import test from 'node:test';
import assert from 'node:assert/strict';
import { coordinates, dragView, world } from '../../resources/js/lib/tripMap.js';

const center = { latitude: 14.5995, longitude: 120.9842 };
const pointer = { x: 100, y: 100, origin: world(center, 12), zoom: 12, width: 450, height: 240, moved: false };

test('pans in the pointer direction at the rendered map scale without waiting for release', () => {
    const first = dragView(pointer, 150, 125);
    const second = dragView({ ...pointer, moved: first.moved }, 200, 150);
    assert.equal(first.moved, true);
    assert.deepEqual(first.view.center, coordinates(pointer.origin[0] - 100, pointer.origin[1] - 50, 12));
    assert.deepEqual(second.view.center, coordinates(pointer.origin[0] - 200, pointer.origin[1] - 100, 12));
    assert.equal(second.view.zoom, 12);
});

test('allows tap jitter but never turns a drag returning to its origin into an endpoint selection', () => {
    assert.equal(dragView(pointer, 102, 102).moved, false);
    assert.equal(dragView({ ...pointer, moved: true }, 100, 100).moved, true);
});
