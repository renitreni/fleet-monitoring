export const WIDTH = 900;
export const HEIGHT = 480;
export function world(point, zoom) {
    const size = 256 * 2 ** zoom;
    const sine = Math.sin((Math.max(-85, Math.min(85, point.latitude)) * Math.PI) / 180);
    return [((point.longitude + 180) / 360) * size, (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * size];
}
export function coordinates(x, y, zoom) {
    const size = 256 * 2 ** zoom;
    return {
        longitude: Math.max(-180, Math.min(180, (x / size) * 360 - 180)),
        latitude: Math.max(-85, Math.min(85, (Math.atan(Math.sinh(Math.PI * (1 - (2 * y) / size))) * 180) / Math.PI)),
    };
}

export function dragView(pointer, clientX, clientY) {
    const dx = clientX - pointer.x;
    const dy = clientY - pointer.y;
    return {
        moved: pointer.moved || Math.hypot(dx, dy) > 5,
        view: {
            center: coordinates(
                pointer.origin[0] - (dx * WIDTH) / pointer.width,
                pointer.origin[1] - (dy * HEIGHT) / pointer.height,
                pointer.zoom
            ),
            zoom: pointer.zoom,
        },
    };
}

export function pinchView(gesture, points) {
    const distance = ([a, b]) => Math.hypot(b.x - a.x, b.y - a.y);
    const midpoint = ([a, b]) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
    const initialDistance = Math.max(1, distance(gesture.points));
    const zoom = Math.max(
        3,
        Math.min(18, gesture.view.zoom + Math.log2(Math.max(1, distance(points)) / initialDistance))
    );
    const start = midpoint(gesture.points);
    const current = midpoint(points);
    const { bounds } = gesture;
    const origin = world(gesture.view.center, gesture.view.zoom);
    const scale = 2 ** (zoom - gesture.view.zoom);
    return {
        zoom,
        center: coordinates(
            (origin[0] + ((start.x - bounds.left) * WIDTH) / bounds.width - WIDTH / 2) * scale -
                ((current.x - bounds.left) * WIDTH) / bounds.width +
                WIDTH / 2,
            (origin[1] + ((start.y - bounds.top) * HEIGHT) / bounds.height - HEIGHT / 2) * scale -
                ((current.y - bounds.top) * HEIGHT) / bounds.height +
                HEIGHT / 2,
            zoom
        ),
    };
}

export function selectMapDesign(settings, selected) {
    return (
        settings.designs.find((design) => design.id === selected) ??
        settings.designs.find((design) => design.id === settings.default) ??
        settings.designs[0]
    );
}
