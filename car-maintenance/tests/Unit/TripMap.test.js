import test from 'node:test';
import assert from 'node:assert/strict';
import { coordinates, dragView, pinchView, world } from '../../resources/js/lib/tripMap.js';

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

const pinch = {
    points: [
        { x: 175, y: 120 },
        { x: 275, y: 120 },
    ],
    view: { center, zoom: 12 },
    bounds: { left: 0, top: 0, width: 450, height: 240 },
};

for (const [name, distance, expectedZoom] of [
    ['spread', 200, 13],
    ['pinch', 50, 11],
    ['smooth spread', 150, 12 + Math.log2(1.5)],
]) {
    test(`${name} adjusts zoom while keeping the midpoint fixed`, () => {
        const view = pinchView(pinch, [
            { x: 225 - distance / 2, y: 120 },
            { x: 225 + distance / 2, y: 120 },
        ]);

        assert.equal(view.zoom, expectedZoom);
        assert.ok(Math.abs(view.center.latitude - center.latitude) < 1e-9);
        assert.ok(Math.abs(view.center.longitude - center.longitude) < 1e-9);
    });
}

test('keeps the geographic anchor under a moving off-center pinch midpoint', () => {
    const gesture = {
        ...pinch,
        points: [
            { x: 100, y: 100 },
            { x: 200, y: 100 },
        ],
    };
    const view = pinchView(gesture, [
        { x: 100, y: 125 },
        { x: 300, y: 125 },
    ]);
    const original = world(center, 12);
    const anchor = coordinates(original[0] - 150, original[1] - 40, 12);
    const projected = world(anchor, view.zoom);
    const origin = world(view.center, view.zoom);

    assert.ok(Math.abs((projected[0] - origin[0] + 450) / 2 - 200) < 1e-7);
    assert.ok(Math.abs((projected[1] - origin[1] + 240) / 2 - 125) < 1e-7);
});

for (const [zoom, distance, expected] of [
    [18, 200, 18],
    [3, 50, 3],
    [12, 0, 5.356143810225276],
]) {
    test(`bounds zoom safely for starting zoom ${zoom} and finger distance ${distance}`, () => {
        const view = pinchView({ ...pinch, view: { center, zoom } }, [
            { x: 225 - distance / 2, y: 120 },
            { x: 225 + distance / 2, y: 120 },
        ]);

        assert.ok(Math.abs(view.zoom - expected) < 1e-9);
        assert.ok(Number.isFinite(view.center.latitude));
        assert.ok(Number.isFinite(view.center.longitude));
    });
}

const { selectMapDesign } = await import('../../resources/js/lib/tripMap.js');
const designs = [{ id: 'standard' }, { id: 'light' }, { id: 'dark' }];

test('uses the admin default until a user chooses another available design', () => {
    const settings = { designs, default: 'dark' };
    assert.equal(selectMapDesign(settings, null).id, 'dark');
    assert.equal(selectMapDesign(settings, 'light').id, 'light');
});

test('falls back to the admin default when the selected design is disabled', () => {
    const settings = { designs: designs.slice(0, 2), default: 'light' };
    assert.equal(selectMapDesign(settings, 'dark').id, 'light');
    assert.equal(selectMapDesign(settings, 'unknown').id, 'light');
});
