export function estimateBlogProfitability(views, rpmInput, costInput) {
    const rpm = Number(rpmInput);
    const cost = Number(costInput);
    if ([rpmInput, costInput].some((value) => value == null || String(value).trim() === '') ||
        ![views, rpm, cost].every(Number.isFinite) || views < 0 || rpm < 0 || cost < 0 ||
        rpm > 1000000 || cost > 1000000000) {
        return null;
    }

    const revenue = views / 1000 * rpm;
    const profit = revenue - cost;
    const breakEvenViews = rpm > 0 ? Math.ceil(cost / rpm * 1000) : cost === 0 ? 0 : null;

    return {
        revenue,
        profit,
        status: profit > 0 ? 'Estimated profit' : profit < 0 ? 'Below break-even' : 'Break-even',
        coverage: cost > 0 ? Math.min(100, revenue / cost * 100) : null,
        breakEvenViews,
        remainingViews: breakEvenViews === null ? null : Math.max(0, breakEvenViews - views),
    };
}
