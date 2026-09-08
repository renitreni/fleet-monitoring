import { useRef, useState } from 'react';
import Button from '@/Components/Button';

const WIDTH = 900;
const HEIGHT = 480;
function world(point, zoom) {
    const size = 256 * 2 ** zoom;
    const sine = Math.sin((Math.max(-85, Math.min(85, point.latitude)) * Math.PI) / 180);
    return [((point.longitude + 180) / 360) * size, (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * size];
}
function coordinates(x, y, zoom) {
    const size = 256 * 2 ** zoom;
    return {
        longitude: Math.max(-180, Math.min(180, (x / size) * 360 - 180)),
        latitude: Math.max(-85, Math.min(85, (Math.atan(Math.sinh(Math.PI * (1 - (2 * y) / size))) * 180) / Math.PI)),
    };
}
function fit(points) {
    if (!points.length) return { center: { latitude: 14.5995, longitude: 120.9842 }, zoom: 12 };
    const latitudes = points.map((p) => Number(p.latitude));
    const longitudes = points.map((p) => Number(p.longitude));
    const center = {
        latitude: (Math.min(...latitudes) + Math.max(...latitudes)) / 2,
        longitude: (Math.min(...longitudes) + Math.max(...longitudes)) / 2,
    };
    let zoom = 16;
    while (zoom > 3) {
        const pixels = points.map((p) => world(p, zoom));
        if (
            Math.max(...pixels.map((p) => p[0])) - Math.min(...pixels.map((p) => p[0])) < WIDTH - 140 &&
            Math.max(...pixels.map((p) => p[1])) - Math.min(...pixels.map((p) => p[1])) < HEIGHT - 120
        )
            break;
        zoom--;
    }
    return { center, zoom };
}

export default function TripMap({
    points = [],
    checkpoints = [],
    participants = [],
    onAdd,
    focusPoint,
    focusLabel = 'Go to coordinates',
    resumePoint,
}) {
    const [view, setView] = useState(() => fit(points));
    const [tileError, setTileError] = useState(false);
    const pointer = useRef(null);
    const { center, zoom } = view;
    const origin = world(center, zoom);
    const left = origin[0] - WIDTH / 2;
    const top = origin[1] - HEIGHT / 2;
    const pixel = (p) => {
        const w = world(p, zoom);
        return [w[0] - left, w[1] - top];
    };
    const tiles = [];
    for (let x = Math.floor(left / 256); x <= Math.floor((left + WIDTH) / 256); x++) {
        for (let y = Math.floor(top / 256); y <= Math.floor((top + HEIGHT) / 256); y++) {
            if (x >= 0 && y >= 0 && x < 2 ** zoom && y < 2 ** zoom) tiles.push({ x, y });
        }
    }
    function pan(dx, dy) {
        setView({ center: coordinates(origin[0] + dx, origin[1] + dy, zoom), zoom });
    }
    function release(event) {
        if (!pointer.current) return;
        const { x, y } = pointer.current;
        pointer.current = null;
        const bounds = event.currentTarget.getBoundingClientRect();
        const dx = ((event.clientX - x) * WIDTH) / bounds.width;
        const dy = ((event.clientY - y) * HEIGHT) / bounds.height;
        if (Math.hypot(dx, dy) > 5) pan(-dx, -dy);
        else if (onAdd)
            onAdd(
                coordinates(
                    left + ((event.clientX - bounds.left) * WIDTH) / bounds.width,
                    top + ((event.clientY - bounds.top) * HEIGHT) / bounds.height,
                    zoom
                )
            );
    }
    return (
        <div className="overflow-hidden border border-[var(--border)] bg-[var(--surface)]">
            <div className="flex flex-wrap items-center gap-2 border-b border-[var(--border)] p-3">
                <Button
                    type="button"
                    variant="secondary"
                    aria-label="Zoom in"
                    disabled={zoom >= 18}
                    onClick={() => setView({ center, zoom: zoom + 1 })}
                >
                    +
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    aria-label="Zoom out"
                    disabled={zoom <= 3}
                    onClick={() => setView({ center, zoom: zoom - 1 })}
                >
                    −
                </Button>
                <Button type="button" variant="secondary" onClick={() => setView(fit(points))}>
                    Fit route
                </Button>
                {focusPoint && (
                    <Button type="button" variant="secondary" onClick={() => setView({ center: focusPoint, zoom: 15 })}>
                        {focusLabel}
                    </Button>
                )}
                <div className="ml-auto flex gap-1">
                    {[
                        [0, -120, '↑', 'north'],
                        [-120, 0, '←', 'west'],
                        [120, 0, '→', 'east'],
                        [0, 120, '↓', 'south'],
                    ].map(([dx, dy, text, label]) => (
                        <button
                            type="button"
                            className="border border-[var(--border)] px-3 py-2"
                            aria-label={`Pan ${label}`}
                            key={label}
                            onClick={() => pan(dx, dy)}
                        >
                            {text}
                        </button>
                    ))}
                </div>
            </div>
            <svg
                viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                className="block w-full touch-none bg-slate-100"
                role="img"
                aria-label={
                    onAdd
                        ? 'Route editor. Tap to add points; drag to pan. Coordinate entry is available below.'
                        : 'Group map showing the planned route, checkpoints, and participant locations.'
                }
                onPointerDown={(event) => {
                    pointer.current = { x: event.clientX, y: event.clientY };
                    event.currentTarget.setPointerCapture(event.pointerId);
                }}
                onPointerUp={release}
                onPointerCancel={() => {
                    pointer.current = null;
                }}
            >
                {tiles.map(({ x, y }) => (
                    <foreignObject
                        key={`${zoom}/${x}/${y}`}
                        x={x * 256 - left}
                        y={y * 256 - top}
                        width="256"
                        height="256"
                    >
                        <img
                            src={`https://tile.openstreetmap.org/${zoom}/${x}/${y}.png`}
                            alt=""
                            draggable={false}
                            width="256"
                            height="256"
                            onError={() => setTileError(true)}
                        />
                    </foreignObject>
                ))}
                <polyline
                    points={points.map((p) => pixel(p).join(',')).join(' ')}
                    fill="none"
                    stroke="white"
                    strokeWidth="9"
                />
                <polyline
                    points={points.map((p) => pixel(p).join(',')).join(' ')}
                    fill="none"
                    stroke="#ee2b24"
                    strokeWidth="4"
                />
                {onAdd &&
                    points.map((p, i) => {
                        const [x, y] = pixel(p);
                        return (
                            <circle key={i} cx={x} cy={y} r="5" fill="#ee2b24">
                                <title>Route point {i + 1}</title>
                            </circle>
                        );
                    })}
                {checkpoints.map((checkpoint, i) => {
                    const point = points[checkpoint.point_index];
                    if (!point) return null;
                    const [x, y] = pixel(point);
                    return (
                        <g key={i} transform={`translate(${x},${y})`}>
                            <title>{checkpoint.name}</title>
                            <circle r="15" fill="#17191c" stroke="white" strokeWidth="3" />
                            <text textAnchor="middle" dy="5" fontSize="13" fontWeight="bold" fill="white">
                                {i + 1}
                            </text>
                        </g>
                    );
                })}
                {resumePoint &&
                    (() => {
                        const [x, y] = pixel(resumePoint);
                        return (
                            <g transform={`translate(${x},${y})`}>
                                <circle r="24" fill="none" stroke="#2563eb" strokeWidth="4" strokeDasharray="5 3" />
                                <title>Your last verified route position. Return here after a tracking gap.</title>
                            </g>
                        );
                    })()}
                {participants
                    .filter((p) => p.location)
                    .map((p) => {
                        const [x, y] = pixel(p.location);
                        return (
                            <g key={p.id} transform={`translate(${x},${y})`} opacity={p.status === 'stale' ? 0.5 : 1}>
                                <title>
                                    {p.name}: {p.status}, ±{Math.round(p.location.accuracy)} m
                                </title>
                                <circle
                                    r="18"
                                    fill={p.status === 'live' ? '#047857' : '#64748b'}
                                    stroke="white"
                                    strokeWidth="3"
                                />
                                <text textAnchor="middle" dy="4" fill="white" fontSize="11" fontWeight="bold">
                                    {p.avatar}
                                </text>
                                <text
                                    y="33"
                                    textAnchor="middle"
                                    fill="#111827"
                                    stroke="white"
                                    strokeWidth="4"
                                    paintOrder="stroke"
                                    fontSize="13"
                                    fontWeight="bold"
                                >
                                    {p.name}
                                </text>
                            </g>
                        );
                    })}
            </svg>
            <div className="flex flex-wrap justify-between gap-2 px-3 py-2 text-xs text-[var(--text-muted)]">
                <span>{onAdd ? 'Tap to add a point · Drag to pan' : 'Drag to pan · Grey markers need attention'}</span>
                <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">
                    © OpenStreetMap contributors
                </a>
            </div>
            {tileError && (
                <p role="status" className="px-3 pb-3 text-sm text-[var(--text-muted)]">
                    Map tiles could not load. Route geometry and the participant list remain available.
                </p>
            )}
        </div>
    );
}
