import { useEffect, useMemo, useRef, useState } from 'react';
import Button from '@/Components/Button';
import { WIDTH, HEIGHT, world, coordinates, dragView, pinchView } from '@/lib/tripMap';

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
    markers = [],
    showRoutePoints = true,
    fitOnChange = false,
    selectionHint = 'Tap to add a point · Drag to pan',
    focusPoint,
    focusLabel = 'Go to coordinates',
    resumePoint,
}) {
    const [view, setView] = useState(() => fit(points));
    const [tileError, setTileError] = useState(false);
    const pointer = useRef(null);
    const touches = useRef(new Map());
    const pinch = useRef(null);
    const [dragging, setDragging] = useState(false);
    const routeBounds = useMemo(
        () => (fitOnChange && points.length ? JSON.stringify(fit(points)) : null),
        [fitOnChange, points]
    );
    useEffect(() => {
        if (routeBounds) setView(JSON.parse(routeBounds));
    }, [routeBounds]);
    const { center, zoom } = view;
    const origin = world(center, zoom);
    const left = origin[0] - WIDTH / 2;
    const top = origin[1] - HEIGHT / 2;
    const pixel = (p) => {
        const w = world(p, zoom);
        return [w[0] - left, w[1] - top];
    };
    const tileZoom = Math.floor(zoom);
    const tileSize = 256 * 2 ** (zoom - tileZoom);
    const tiles = [];
    for (let x = Math.floor(left / tileSize); x <= Math.floor((left + WIDTH) / tileSize); x++) {
        for (let y = Math.floor(top / tileSize); y <= Math.floor((top + HEIGHT) / tileSize); y++) {
            if (x >= 0 && y >= 0 && x < 2 ** tileZoom && y < 2 ** tileZoom) tiles.push({ x, y });
        }
    }
    function pan(dx, dy) {
        setView({ center: coordinates(origin[0] + dx, origin[1] + dy, zoom), zoom });
    }
    function startPointer(event) {
        if (event.button !== 0 || touches.current.size >= 2) return;
        event.preventDefault();
        touches.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
        event.currentTarget.setPointerCapture(event.pointerId);
        const bounds = event.currentTarget.getBoundingClientRect();
        if (touches.current.size === 2) {
            pointer.current = null;
            pinch.current = { points: [...touches.current.values()], view, bounds };
            setDragging(true);
        } else {
            pointer.current = {
                x: event.clientX,
                y: event.clientY,
                id: event.pointerId,
                origin,
                zoom,
                width: bounds.width,
                height: bounds.height,
                left: bounds.left,
                top: bounds.top,
                moved: false,
            };
        }
    }
    function movePointer(event) {
        if (!touches.current.has(event.pointerId)) return;
        touches.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
        if (pinch.current) {
            if (touches.current.size === 2) {
                setView(pinchView(pinch.current, [...touches.current.values()]));
            }
            return;
        }
        if (!pointer.current || pointer.current.id !== event.pointerId) return;
        const result = dragView(pointer.current, event.clientX, event.clientY);
        pointer.current.moved = result.moved;
        if (result.moved) {
            setDragging(true);
            setView(result.view);
        }
    }
    function release(event) {
        if (!touches.current.has(event.pointerId)) return;
        if (event.type === 'pointerup') movePointer(event);
        touches.current.delete(event.pointerId);
        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }
        if (pinch.current || event.type !== 'pointerup') {
            pointer.current = null;
            if (!touches.current.size) pinch.current = null;
            setDragging(false);
            return;
        }
        if (!pointer.current || pointer.current.id !== event.pointerId) return;
        const gesture = pointer.current;
        pointer.current = null;
        setDragging(false);
        if (!gesture.moved && onAdd) {
            onAdd(
                coordinates(
                    gesture.origin[0] - WIDTH / 2 + ((event.clientX - gesture.left) * WIDTH) / gesture.width,
                    gesture.origin[1] - HEIGHT / 2 + ((event.clientY - gesture.top) * HEIGHT) / gesture.height,
                    gesture.zoom
                )
            );
        }
    }
    return (
        <div className="overflow-hidden border border-[var(--border)] bg-[var(--surface)]">
            <div className="flex flex-wrap items-center gap-2 border-b border-[var(--border)] p-3">
                <Button
                    type="button"
                    variant="secondary"
                    aria-label="Zoom in"
                    disabled={zoom >= 18}
                    onClick={() => setView({ center, zoom: Math.min(18, zoom + 1) })}
                >
                    +
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    aria-label="Zoom out"
                    disabled={zoom <= 3}
                    onClick={() => setView({ center, zoom: Math.max(3, zoom - 1) })}
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
                className={`block w-full touch-none select-none bg-slate-100 ${dragging ? 'cursor-grabbing' : 'cursor-grab'}`}
                role="img"
                aria-label={
                    onAdd
                        ? `Route editor. ${selectionHint}. Pinch to zoom. Coordinate entry is available.`
                        : 'Group map showing the planned route, checkpoints, and participant locations.'
                }
                onPointerDown={startPointer}
                onPointerMove={movePointer}
                onPointerUp={release}
                onPointerCancel={release}
                onLostPointerCapture={release}
            >
                {tiles.map(({ x, y }) => (
                    <foreignObject
                        key={`${tileZoom}/${x}/${y}`}
                        x={x * tileSize - left}
                        y={y * tileSize - top}
                        width={tileSize}
                        height={tileSize}
                    >
                        <img
                            src={`https://tile.openstreetmap.org/${tileZoom}/${x}/${y}.png`}
                            alt=""
                            draggable={false}
                            width={tileSize}
                            height={tileSize}
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
                    showRoutePoints &&
                    points.map((p, i) => {
                        const [x, y] = pixel(p);
                        return (
                            <circle key={i} cx={x} cy={y} r="5" fill="#ee2b24">
                                <title>Route point {i + 1}</title>
                            </circle>
                        );
                    })}
                {markers.map((marker) => {
                    const [x, y] = pixel(marker);
                    return (
                        <g key={marker.label} transform={`translate(${x},${y})`}>
                            <title>{marker.name}</title>
                            <circle r="15" fill="#17191c" stroke="white" strokeWidth="3" />
                            <text textAnchor="middle" dy="5" fontSize="13" fontWeight="bold" fill="white">
                                {marker.label}
                            </text>
                        </g>
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
                <span>{onAdd ? selectionHint : 'Drag to pan · Grey markers need attention'} · Pinch to zoom</span>
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
