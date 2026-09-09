import test from 'node:test';
import assert from 'node:assert/strict';
import {
    decodeShape,
    distance,
    findDrivingRoute,
    parseEndpoint,
    prepareRoute,
} from '../../resources/js/lib/tripRouting.js';

const start = { latitude: 0, longitude: 0 };
const end = { latitude: 0.002, longitude: 0 };
const response = { trip: { status: 0, legs: [{ shape: '??o}@?o}@?' }], summary: { length: 0.222 } } };

test('accepts coordinate pairs and rejects missing, invalid and out of range endpoints', () => {
    assert.deepEqual(parseEndpoint(' 14.5995, 120.9842 '), { latitude: 14.5995, longitude: 120.9842 });
    assert.deepEqual(parseEndpoint('0, 0'), start);
    for (const value of ['', ',', '1,', 'Manila', '1,2,3', '86,0', '0,181', 'Infinity,1']) {
        assert.equal(parseEndpoint(value), null);
    }
});

test('decodes road geometry at six decimal precision and rejects truncated geometry', () => {
    assert.deepEqual(decodeShape('??o}@?o}@?'), [start, { latitude: 0.001, longitude: 0 }, end]);
    assert.throws(() => decodeShape('??o}@'), /incomplete/);
    assert.throws(() => decodeShape(null), /invalid/);
});

test('preserves endpoints and bends while removing near duplicates and splitting long road segments', () => {
    const bend = { latitude: 0, longitude: 0.02 };
    const finish = { latitude: 0.01, longitude: 0.02 };
    const points = prepareRoute([
        start,
        start,
        { latitude: 0, longitude: 0.00001 },
        bend,
        { latitude: 0.00999, longitude: 0.02 },
        finish,
    ]);
    assert.deepEqual(points[0], start);
    assert.deepEqual(points.at(-1), finish);
    assert.ok(points.some((point) => point.latitude === bend.latitude && point.longitude === bend.longitude));
    for (let i = 1; i < points.length; i++) {
        assert.ok(distance(points[i - 1], points[i]) >= 10);
        assert.ok(distance(points[i - 1], points[i]) <= 1000);
    }
});

test('rejects empty, too short, too long and unsupported routes before saving', () => {
    assert.throws(() => prepareRoute([]), /invalid/);
    assert.throws(() => prepareRoute([start, start]), /100 metres/);
    assert.throws(() => prepareRoute([start, { latitude: 5, longitude: 0 }]), /500 kilometres/);
    assert.throws(() => prepareRoute([start, { latitude: NaN, longitude: 0 }]), /invalid/);
    assert.throws(
        () =>
            prepareRoute([
                { latitude: 0, longitude: 179.9 },
                { latitude: 0, longitude: -179.9 },
            ]),
        /date line/
    );
    const detailed = Array.from({ length: 2001 }, (_, i) => ({ latitude: i * 0.0001, longitude: 0 }));
    assert.throws(() => prepareRoute(detailed), /too detailed/);
});

test('requests shortest-distance driving and returns geometry ready to save', async () => {
    const signal = new AbortController().signal;
    const result = await findDrivingRoute(start, end, signal, async (url, options) => {
        const query = JSON.parse(new URL(url).searchParams.get('json'));
        assert.equal(query.costing, 'auto');
        assert.equal(query.costing_options.auto.shortest, true);
        assert.deepEqual(
            query.locations.map(({ lat, lon }) => [lat, lon]),
            [
                [0, 0],
                [0.002, 0],
            ]
        );
        assert.equal(options.signal, signal);
        return { ok: true, json: async () => response };
    });
    assert.deepEqual(result.points[0], start);
    assert.deepEqual(result.points.at(-1), end);
    assert.equal(result.distanceKm, 0.222);
});

test('reports network failure, no route, malformed geometry and distant road snapping', async () => {
    await assert.rejects(
        findDrivingRoute(start, end, undefined, async () => ({ ok: false })),
        /Could not find/
    );
    await assert.rejects(
        findDrivingRoute(start, end, undefined, async () => ({ ok: true, json: async () => ({}) })),
        /No driving route/
    );
    await assert.rejects(
        findDrivingRoute(start, end, undefined, async () => {
            throw new Error('Offline');
        }),
        /Offline/
    );
    await assert.rejects(
        findDrivingRoute({ latitude: 1, longitude: 0 }, end, undefined, async () => ({
            ok: true,
            json: async () => response,
        })),
        /500 metres/
    );
});

test('rejects a missing distance and preserves cancellation errors', async () => {
    await assert.rejects(
        findDrivingRoute(start, end, undefined, async () => ({
            ok: true,
            json: async () => ({ trip: { ...response.trip, summary: {} } }),
        })),
        /invalid distance/
    );
    const cancellation = new Error('Request cancelled');
    cancellation.name = 'AbortError';
    await assert.rejects(
        findDrivingRoute(start, end, undefined, async () => {
            throw cancellation;
        }),
        { name: 'AbortError' }
    );
});
