import { formatTime } from '@/lib/routePreview';

export default function RouteLeaderboard({ rows = [] }) {
    if (!rows.length)
        return (
            <p className="border border-dashed border-[var(--border)] p-8 text-[var(--text-muted)]">
                No shared times yet. Complete an attempt and opt in to set the first record.
            </p>
        );
    return (
        <div className="overflow-x-auto border border-[var(--border)]">
            <table className="w-full min-w-[600px] text-left text-sm">
                <caption className="sr-only">Best verified completion time per user. Equal times share a rank.</caption>
                <thead className="bg-[var(--surface-muted)] text-[10px] uppercase tracking-widest">
                    <tr>
                        {['Rank', 'Driver', 'Route covered', 'Best time', 'Gap to first', 'Completed'].map((label) => (
                            <th key={label} className="p-4">
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border)]">
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <td className="p-4 font-mono font-bold text-[var(--accent)]">
                                {String(row.rank).padStart(2, '0')}
                            </td>
                            <td className="p-4 font-bold">{row.name}</td>
                            <td className="p-4 font-mono">100%</td>
                            <td className="p-4 font-mono">{formatTime(row.elapsed_ms)}</td>
                            <td className="p-4 font-mono text-[var(--text-muted)]">
                                {row.gap_ms ? `+${formatTime(row.gap_ms)}` : '—'}
                            </td>
                            <td className="p-4 text-[var(--text-muted)]">
                                {row.finished_at ? new Date(row.finished_at).toLocaleDateString() : '—'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
