export function parseEndpoint(value) {
    const parts = value.split(',').map((part) => part.trim());
    if (parts.length !== 2 || parts.some((part) => !part)) return null;
    const [latitude, longitude] = parts.map(Number);
    return Number.isFinite(latitude) &&
        Number.isFinite(longitude) &&
        Math.abs(latitude) <= 85 &&
        Math.abs(longitude) <= 180
        ? { latitude, longitude }
        : null;
}

export function distance(a, b) {
    const radians = Math.PI / 180;
    const h =
        Math.sin(((b.latitude - a.latitude) * radians) / 2) ** 2 +
        Math.cos(a.latitude * radians) *
            Math.cos(b.latitude * radians) *
            Math.sin(((b.longitude - a.longitude) * radians) / 2) ** 2;
    return 6371000 * 2 * Math.asin(Math.sqrt(Math.min(1, h)));
}

export function decodeShape(shape) {
    if (typeof shape !== 'string') throw new Error('The routing service returned an invalid route. Please retry.');
    let index = 0;
    let latitude = 0;
    let longitude = 0;
    const points = [];
    function next() {
        let result = 0;
        let shift = 0;
        let byte;
        do {
            if (index >= shape.length || shift > 30)
                throw new Error('The routing service returned an incomplete route. Please retry.');
            byte = shape.charCodeAt(index++) - 63;
            if (byte < 0 || byte > 63) throw new Error('The routing service returned an invalid route. Please retry.');
            result |= (byte & 31) << shift;
            shift += 5;
        } while (byte >= 32);
        return result & 1 ? ~(result >> 1) : result >> 1;
    }
    while (index < shape.length) {
        latitude += next();
        longitude += next();
        points.push({ latitude: latitude / 1e6, longitude: longitude / 1e6 });
    }
    return points;
}

export function prepareRoute(rawPoints) {
    if (
        rawPoints.length < 2 ||
        rawPoints.some(
            (p) =>
                !Number.isFinite(p.latitude) ||
                !Number.isFinite(p.longitude) ||
                Math.abs(p.latitude) > 85 ||
                Math.abs(p.longitude) > 180
        )
    ) {
        throw new Error('The routing service returned an invalid route. Please retry.');
    }
    // Keep the course within ten metres of the road geometry while meeting the trip point spacing rules.
    const retained = [rawPoints[0]];
    for (const point of rawPoints.slice(1, -1)) {
        if (distance(retained.at(-1), point) >= 10) retained.push(point);
    }
    const finish = rawPoints.at(-1);
    while (retained.length > 1 && distance(retained.at(-1), finish) < 10) retained.pop();
    retained.push(finish);
    const points = [retained[0]];
    let total = 0;
    for (let i = 1; i < retained.length; i++) {
        const a = retained[i - 1];
        const b = retained[i];
        const length = distance(a, b);
        total += length;
        if (Math.abs(a.longitude - b.longitude) > 180)
            throw new Error('Routes crossing the date line are not supported.');
        const segments = Math.max(1, Math.ceil(length / 500));
        if (points.length + segments > 2000)
            throw new Error('This route is too detailed to save. Choose closer endpoints.');
        for (let step = 1; step <= segments; step++) {
            points.push(
                step === segments
                    ? b
                    : {
                          latitude: a.latitude + ((b.latitude - a.latitude) * step) / segments,
                          longitude: a.longitude + ((b.longitude - a.longitude) * step) / segments,
                      }
            );
        }
    }
    if (total < 100 || total > 500000) throw new Error('Choose a driving route between 100 metres and 500 kilometres.');
    return points;
}

export async function findDrivingRoute(start, end, signal, request = fetch) {
    const url = new URL('https://valhalla1.openstreetmap.de/route');
    url.searchParams.set(
        'json',
        JSON.stringify({
            locations: [start, end].map(({ latitude, longitude }) => ({
                lat: latitude,
                lon: longitude,
                search_cutoff: 500,
            })),
            costing: 'auto',
            costing_options: { auto: { shortest: true } },
            units: 'kilometers',
            directions_type: 'none',
        })
    );
    const response = await request(url.toString(), { signal });
    if (!response.ok)
        throw new Error(
            'Could not find a driving route. Check that both endpoints are near connected roads, then retry.'
        );
    const result = await response.json();
    if (result.trip?.status !== 0 || result.trip.legs?.length !== 1)
        throw new Error('No driving route was found between these endpoints.');
    if (!Number.isFinite(result.trip.summary?.length) || result.trip.summary.length <= 0) {
        throw new Error('The routing service returned an invalid distance. Please retry.');
    }
    const points = prepareRoute(decodeShape(result.trip.legs[0].shape));
    if (distance(start, points[0]) > 500 || distance(end, points.at(-1)) > 500)
        throw new Error('Choose endpoints within 500 metres of a drivable road.');
    return { points, distanceKm: result.trip.summary.length };
}
