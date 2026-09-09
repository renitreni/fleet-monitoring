import { useId } from 'react';
import { previewPoints } from '@/lib/routePreview';

export default function RoutePreview({ points = [], name = 'Route' }) {
    const gridId = useId();
    const path = previewPoints(points);
    return (
        <div className="relative overflow-hidden bg-[var(--surface-muted)] text-[var(--accent)]">
            <svg
                viewBox="0 0 480 240"
                role="img"
                aria-label={`${name}: generated course with start and finish`}
                className="w-full"
            >
                <defs>
                    <pattern id={gridId} width="24" height="24" patternUnits="userSpaceOnUse">
                        <path d="M 24 0 L 0 0 0 24" fill="none" stroke="currentColor" strokeOpacity=".09" />
                    </pattern>
                </defs>
                <rect width="480" height="240" fill={`url(#${gridId})`} />
                {path.length > 1 ? (
                    <>
                        <polyline
                            points={path.map((point) => point.join(',')).join(' ')}
                            fill="none"
                            stroke="currentColor"
                            strokeOpacity=".12"
                            strokeWidth="16"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                        <polyline
                            points={path.map((point) => point.join(',')).join(' ')}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="4"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                        {[path[0], path.at(-1)].map(([x, y], index) => (
                            <g key={index}>
                                <circle
                                    cx={x}
                                    cy={y}
                                    r="12"
                                    fill="var(--surface)"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                />
                                <text
                                    x={x}
                                    y={y + 4}
                                    textAnchor="middle"
                                    fill="currentColor"
                                    fontSize="11"
                                    fontWeight="800"
                                >
                                    {index ? 'F' : 'S'}
                                </text>
                            </g>
                        ))}
                    </>
                ) : (
                    <text x="240" y="120" textAnchor="middle" fill="currentColor" fontSize="14">
                        Route preview unavailable
                    </text>
                )}
            </svg>
            <span className="absolute bottom-3 left-4 text-[9px] font-bold uppercase tracking-widest text-[var(--text-muted)]">
                Course overview · S start / F finish
            </span>
        </div>
    );
}
