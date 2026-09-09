import test from 'node:test';
import assert from 'node:assert/strict';
import { publishRoute } from '../../resources/js/lib/publishRoute.js';

test('publishes course details and geometry with the React form API and preserves response callbacks', () => {
    const details = { name: 'Coastal drive', description: 'Meet at the start' };
    const points = [
        { latitude: 14.6, longitude: 121, label: 'Start' },
        { latitude: 14.61, longitude: 121.01, label: 'Finish' },
    ];
    const checkpoints = [
        { name: 'Start', point_index: 0 },
        { name: 'Finish', point_index: 1 },
    ];
    const options = { onSuccess() {} };
    let transform;
    const requests = [];
    const form = {
        transform(callback) {
            transform = callback;
        },
        post(url, callbacks) {
            requests.push({ url, data: transform(details), callbacks });
        },
    };

    publishRoute(form, '/admin/trips', points, checkpoints, options);

    assert.deepEqual(requests, [
        {
            url: '/admin/trips',
            data: {
                name: 'Coastal drive',
                description: 'Meet at the start',
                route_points: [
                    { latitude: 14.6, longitude: 121 },
                    { latitude: 14.61, longitude: 121.01 },
                ],
                checkpoints,
            },
            callbacks: options,
        },
    ]);
    assert.equal(requests[0].callbacks.onSuccess, options.onSuccess);
});
