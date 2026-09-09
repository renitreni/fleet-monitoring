import { world } from './tripMap.js';

export function previewPoints(points = [], width = 480, height = 240, padding = 36) {
    if (points.length < 2) return [];
    const projected = points.map((point) => world(point, 0));
    const xs = projected.map(([x]) => x);
    const ys = projected.map(([, y]) => y);
    const minX = Math.min(...xs);
    const minY = Math.min(...ys);
    const spanX = Math.max(...xs) - minX;
    const spanY = Math.max(...ys) - minY;
    const scale = Math.min((width - padding * 2) / (spanX || 1e-9), (height - padding * 2) / (spanY || 1e-9));
    return projected.map(([x, y]) => [
        (width - spanX * scale) / 2 + (x - minX) * scale,
        (height - spanY * scale) / 2 + (y - minY) * scale,
    ]);
}

export function formatTime(value) {
    if (value === null || value === undefined) return 'No time yet';
    const milliseconds = Math.max(0, Math.round(Number(value)));
    const seconds = Math.floor(milliseconds / 1000);
    return `${String(Math.floor(seconds / 3600)).padStart(2, '0')}:${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}.${String(milliseconds % 1000).padStart(3, '0')}`;
}
