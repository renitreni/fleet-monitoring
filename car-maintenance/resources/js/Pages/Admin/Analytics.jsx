import { Form, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const numberFormatter = new Intl.NumberFormat();
const dateFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

function formatNumber(value) {
    return numberFormatter.format(value ?? 0);
}

function StatCard({ index, label, value, suffix = '', note }) {
    return (
        <article className="border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-7">
            <div className="flex items-center justify-between gap-4 text-[10px] font-black uppercase tracking-[0.2em] text-[var(--text-muted)]">
                <span>/{index}</span>
                <span>{label}</span>
            </div>
            <p className="mt-8 font-mono text-5xl font-bold tracking-[-0.07em] sm:text-6xl">
                {formatNumber(value)}
                {suffix && <span className="ml-1 text-2xl text-[var(--accent)]">{suffix}</span>}
            </p>
            <p className="mt-4 text-xs leading-5 text-[var(--text-muted)]">{note}</p>
        </article>
    );
}

function TrendChart({ data }) {
    const width = 760;
    const height = 260;
    const padding = 28;
    const maximum = Math.max(1, ...data.flatMap((point) => [point.page_views, point.sessions]));
    const x = (index) => padding + (index / Math.max(1, data.length - 1)) * (width - padding * 2);
    const y = (value) => height - padding - (value / maximum) * (height - padding * 2);
    const path = (key) =>
        data.map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(point[key])}`).join(' ');
    const labelIndexes = [...new Set([0, Math.floor((data.length - 1) / 2), data.length - 1])];

    return (
        <div>
            <div className="mb-5 flex flex-wrap gap-5 text-[10px] font-black uppercase tracking-[0.15em]">
                <span className="flex items-center gap-2">
                    <span className="h-0.5 w-6 bg-[var(--accent)]" /> Page views
                </span>
                <span className="flex items-center gap-2 text-[var(--text-muted)]">
                    <span className="h-0.5 w-6 bg-current" /> Sessions
                </span>
            </div>
            <div className="overflow-x-auto">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="min-w-[620px]"
                    role="img"
                    aria-label="Daily page views and unique sessions over the selected period"
                >
                    {[0, 0.5, 1].map((ratio) => (
                        <g key={ratio}>
                            <line
                                x1={padding}
                                x2={width - padding}
                                y1={y(maximum * ratio)}
                                y2={y(maximum * ratio)}
                                stroke="var(--border)"
                                strokeWidth="1"
                            />
                            <text
                                x={padding}
                                y={y(maximum * ratio) - 7}
                                fill="var(--text-muted)"
                                fontSize="10"
                                fontFamily="monospace"
                            >
                                {Math.round(maximum * ratio)}
                            </text>
                        </g>
                    ))}
                    <path d={path('sessions')} fill="none" stroke="var(--text-muted)" strokeWidth="2" />
                    <path d={path('page_views')} fill="none" stroke="var(--accent)" strokeWidth="3" />
                    {labelIndexes.map((index) => (
                        <text
                            key={data[index].date}
                            x={x(index)}
                            y={height - 5}
                            textAnchor={index === 0 ? 'start' : index === data.length - 1 ? 'end' : 'middle'}
                            fill="var(--text-muted)"
                            fontSize="10"
                            fontFamily="monospace"
                        >
                            {dateFormatter.format(new Date(`${data[index].date}T00:00:00`))}
                        </text>
                    ))}
                </svg>
            </div>
        </div>
    );
}

function Ranking({ title, eyebrow, rows, empty, renderLabel }) {
    const maximum = Math.max(1, ...rows.map((row) => row.views));

    return (
        <section className="border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-8">
            <p className="eyebrow">{eyebrow}</p>
            <h2 className="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">{title}</h2>
            {rows.length === 0 ? (
                <p className="mt-8 text-sm text-[var(--text-muted)]">{empty}</p>
            ) : (
                <ol className="mt-7 space-y-5">
                    {rows.map((row, index) => (
                        <li key={`${renderLabel(row)}-${index}`}>
                            <div className="flex items-end justify-between gap-4 text-sm">
                                <span className="min-w-0 truncate font-bold">{renderLabel(row)}</span>
                                <span className="font-mono text-xs text-[var(--text-muted)]">
                                    {formatNumber(row.views)}
                                </span>
                            </div>
                            <div className="mt-2 h-1.5 bg-[var(--surface-muted)]">
                                <div
                                    className="h-full bg-[var(--accent)]"
                                    style={{ width: `${Math.max(3, (row.views / maximum) * 100)}%` }}
                                />
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

export default function Analytics({ report, filters }) {
    const presets = [
        { value: '7', label: '7 days' },
        { value: '30', label: '30 days' },
        { value: '90', label: '90 days' },
    ];
    const productMetrics = [
        ['Cars added', report.product.cars_added],
        ['Oil changes', report.product.oil_changes],
        ['Route joins', report.product.route_joins],
        ['Attempts completed', report.product.completed_attempts],
        ['Posts published', report.product.published_posts],
        ['Blog views', report.product.blog_views],
    ];

    return (
        <AuthenticatedLayout
            title="Website analytics"
            header={
                <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                    <div>
                        <p className="eyebrow">Admin / Intelligence</p>
                        <h1 className="mt-2 text-3xl font-black uppercase tracking-[-0.04em]">Website analytics</h1>
                        <p className="mt-3 text-sm text-[var(--text-muted)]">
                            Privacy-safe traffic, acquisition, and product activity.
                        </p>
                    </div>
                    <p className="text-xs text-[var(--text-muted)]">
                        Updated {dateTimeFormatter.format(new Date(report.generated_at))}
                    </p>
                </div>
            }
        >
            <div className="motologic-grid pointer-events-none fixed inset-0 opacity-20" />
            <div className="relative mx-auto max-w-[1480px] px-5 py-8 sm:px-8 lg:px-12 lg:py-12">
                <section className="flex flex-col gap-5 border border-[var(--border)] bg-[var(--surface)] p-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--text-muted)]">
                            Reporting period
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {presets.map((preset) => (
                                <Link
                                    key={preset.value}
                                    href={`/admin/analytics?period=${preset.value}`}
                                    className={`border px-4 py-2 text-xs font-black uppercase tracking-[0.12em] ${filters.period === preset.value ? 'border-[var(--accent)] bg-[var(--accent)] text-white' : 'border-[var(--border)] hover:border-[var(--accent)]'}`}
                                >
                                    {preset.label}
                                </Link>
                            ))}
                        </div>
                    </div>
                    <Form action="/admin/analytics" method="get" className="flex flex-wrap items-end gap-3">
                        <input type="hidden" name="period" value="custom" />
                        <label className="grid gap-1 text-[10px] font-black uppercase tracking-[0.14em] text-[var(--text-muted)]">
                            From
                            <input
                                type="date"
                                name="from"
                                defaultValue={filters.from}
                                required
                                className="border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text)]"
                            />
                        </label>
                        <label className="grid gap-1 text-[10px] font-black uppercase tracking-[0.14em] text-[var(--text-muted)]">
                            To
                            <input
                                type="date"
                                name="to"
                                defaultValue={filters.to}
                                required
                                className="border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text)]"
                            />
                        </label>
                        <button
                            type="submit"
                            className="bg-[var(--text)] px-5 py-2.5 text-xs font-black uppercase tracking-[0.14em] text-[var(--background)]"
                        >
                            Apply
                        </button>
                    </Form>
                </section>

                <section className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        index="01"
                        label="Page views"
                        value={report.overview.page_views}
                        note="Successful page loads"
                    />
                    <StatCard
                        index="02"
                        label="Sessions"
                        value={report.overview.unique_sessions}
                        note="Privacy-safe session count"
                    />
                    <StatCard
                        index="03"
                        label="Registrations"
                        value={report.overview.registrations}
                        note="Accounts created in this period"
                    />
                    <StatCard
                        index="04"
                        label="Activation"
                        value={report.overview.activation_rate}
                        suffix="%"
                        note="New accounts that added a car"
                    />
                </section>

                <section className="mt-6 border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-8">
                    <div className="mb-8 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <p className="eyebrow">Traffic trend</p>
                            <h2 className="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">Daily attention</h2>
                        </div>
                        {report.overview.page_views === 0 && (
                            <p className="text-xs text-[var(--text-muted)]">
                                Traffic collection starts after deployment.
                            </p>
                        )}
                    </div>
                    <TrendChart data={report.trend} />
                </section>

                <section className="mt-6 grid gap-4 lg:grid-cols-2">
                    <Ranking
                        eyebrow="Content"
                        title="Top pages"
                        rows={report.top_pages}
                        empty="No page views were recorded in this period."
                        renderLabel={(row) => row.route_uri}
                    />
                    <Ranking
                        eyebrow="Acquisition"
                        title="Top referrers"
                        rows={report.top_referrers}
                        empty="No external referrers were recorded in this period."
                        renderLabel={(row) => row.host}
                    />
                </section>

                <section className="mt-6 border border-[var(--border)] bg-[var(--surface)] p-6 sm:p-8">
                    <p className="eyebrow">Product engagement</p>
                    <h2 className="mt-3 text-2xl font-black uppercase tracking-[-0.03em]">Actions that matter</h2>
                    <dl className="mt-7 grid gap-px bg-[var(--border)] sm:grid-cols-2 lg:grid-cols-3">
                        {productMetrics.map(([label, value]) => (
                            <div key={label} className="bg-[var(--surface)] p-5">
                                <dt className="text-[10px] font-black uppercase tracking-[0.15em] text-[var(--text-muted)]">
                                    {label}
                                </dt>
                                <dd className="mt-4 font-mono text-3xl font-bold">{formatNumber(value)}</dd>
                            </div>
                        ))}
                    </dl>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
