import { useState } from 'react';
import { estimateBlogProfitability } from '../lib/blogProfitability';

export default function BlogProfitability({ views, from, to }) {
    const [rpm, setRpm] = useState('');
    const [cost, setCost] = useState('');
    const [currency, setCurrency] = useState('PHP');
    const estimate = estimateBlogProfitability(views, rpm, cost);
    const money = (value) => new Intl.NumberFormat('en', { style: 'currency', currency }).format(value);
    const number = (value) => new Intl.NumberFormat('en').format(value);
    const inputClass = 'w-full min-w-0 border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--text)]';

    return (
        <section className="mt-6 border-y border-[var(--border)] py-8" aria-labelledby="blog-profitability-title">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="eyebrow">Ad revenue estimate</p>
                    <h2 id="blog-profitability-title" className="mt-3 text-xl font-bold">Blog ad profitability</h2>
                    <p className="mt-2 text-sm text-[var(--text-muted)]">
                        {number(views)} article views &middot; {from} to {to}
                    </p>
                </div>
                <span className="text-sm font-bold" role="status">{estimate?.status ?? 'Awaiting assumptions'}</span>
            </div>
            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                <label className="grid gap-2 text-sm">
                    Currency
                    <select value={currency} onChange={(event) => setCurrency(event.target.value)} className={inputClass}>
                        <option value="PHP">PHP</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </label>
                <label className="grid gap-2 text-sm">
                    Expected page RPM ({currency})
                    <input type="number" min="0" max="1000000" step="any" placeholder="Not set" value={rpm}
                        onChange={(event) => setRpm(event.target.value)} className={inputClass} aria-describedby="blog-estimate-basis" />
                </label>
                <label className="grid gap-2 text-sm">
                    Costs for this period ({currency})
                    <input type="number" min="0" max="1000000000" step="any" placeholder="Not set" value={cost}
                        onChange={(event) => setCost(event.target.value)} className={inputClass} aria-describedby="blog-estimate-basis" />
                </label>
            </div>
            <p id="blog-estimate-basis" className="mt-3 text-xs leading-5 text-[var(--text-muted)]">
                Page RPM is revenue per 1,000 article views, including views without ads. Costs include hosting,
                content, and promotion for this date range. Amounts use the selected currency without conversion.
                Estimates are not actual ad earnings.
            </p>
            {!estimate ? (
                <p className="mt-6 text-sm text-[var(--text-muted)]">A valid RPM and period cost are required. Both can be zero.</p>
            ) : (
                <div className="mt-6" aria-live="polite">
                    {estimate.coverage !== null ? (
                        <>
                            <div className="mb-2 flex flex-wrap justify-between gap-2 text-sm">
                                <span>Cost coverage</span>
                                <span>{number(Math.round(estimate.coverage))}% of break-even</span>
                            </div>
                            <div role="meter" aria-label="Cost coverage" aria-valuemin={0} aria-valuemax={100}
                                aria-valuenow={estimate.coverage} className="h-3 overflow-hidden bg-[var(--surface-muted)]">
                                <div className={`h-full ${estimate.profit >= 0 ? 'bg-emerald-500' : 'bg-amber-500'}`}
                                    style={{ width: `${estimate.coverage}%` }} />
                            </div>
                        </>
                    ) : <p className="text-sm text-[var(--text-muted)]">No costs to recover.</p>}
                    <dl className="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            ['Estimated revenue', money(estimate.revenue)],
                            ['Estimated net profit', money(estimate.profit)],
                            ['Break-even article views', estimate.breakEvenViews === null ? 'Not reachable at zero RPM' : number(estimate.breakEvenViews)],
                            ['More views to break even', estimate.remainingViews === null ? 'Not reachable at zero RPM' : number(estimate.remainingViews)],
                        ].map(([label, value]) => (
                            <div key={label} className="min-w-0">
                                <dt className="text-xs text-[var(--text-muted)]">{label}</dt>
                                <dd className="mt-2 break-words font-mono text-lg font-bold">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {views === 0 && <p className="mt-5 text-sm text-[var(--text-muted)]">No article views recorded in this period.</p>}
                </div>
            )}
        </section>
    );
}
