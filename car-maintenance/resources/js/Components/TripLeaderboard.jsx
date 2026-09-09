export default function TripLeaderboard({ rows = [], checkpointCount, publicView = false }) {
    if (!rows.length)
        return (
            <p className="border border-dashed border-[var(--border)] p-6 text-sm text-[var(--text-muted)]">
                {publicView
                    ? 'No shared results yet. Participants choose whether to appear here.'
                    : 'Your group’s progress will appear here.'}
            </p>
        );
    return (
        <div className="overflow-x-auto border border-[var(--border)]">
            <table className="w-full min-w-[520px] text-left text-sm">
                <caption className="sr-only">
                    Ranked by verified route progress, then ordered checkpoints. Equal scores share a rank.
                </caption>
                <thead className="bg-[var(--surface-muted)] text-[10px] uppercase tracking-widest">
                    <tr>
                        <th className="p-4">Rank</th>
                        <th className="p-4">Driver</th>
                        <th className="p-4">Route covered</th>
                        <th className="p-4">Checkpoints</th>
                        <th className="p-4">{publicView ? 'Result' : 'Connection'}</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-[var(--border)]">
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <td className="p-4 font-mono font-bold">{String(row.rank).padStart(2, '0')}</td>
                            <td className="p-4">
                                <div className="flex items-center gap-3">
                                    <span
                                        aria-label={`${row.name} avatar`}
                                        className="grid h-9 w-9 shrink-0 place-items-center bg-[var(--accent)] text-xs font-black text-white"
                                    >
                                        {row.avatar}
                                    </span>
                                    <span className="font-bold">{row.name}</span>
                                </div>
                            </td>
                            <td className="min-w-36 p-4">
                                <span className="font-mono">{row.progress}%</span>
                                <div className="mt-2 h-1 bg-[var(--border)]">
                                    <div className="h-full bg-[var(--accent)]" style={{ width: `${row.progress}%` }} />
                                </div>
                            </td>
                            <td className="p-4 font-mono">
                                {row.checkpoints_completed} / {checkpointCount}
                            </td>
                            <td className="p-4">
                                <span className="capitalize">
                                    {publicView ? (row.completed ? 'Completed' : 'In progress') : row.status}
                                </span>
                                {!publicView && row.last_seen_at && (
                                    <span className="mt-1 block text-xs text-[var(--text-muted)]">
                                        Last update {new Date(row.last_seen_at).toLocaleTimeString()}
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
