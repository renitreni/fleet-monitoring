import test from 'node:test';
import assert from 'node:assert/strict';
import { estimateBlogProfitability } from '../../resources/js/lib/blogProfitability.js';

test('article traffic below break-even shows the loss and additional views needed', () => {
    const result = estimateBlogProfitability(1000, '5', '20');

    assert.deepEqual(result, { revenue: 5, profit: -15, status: 'Below break-even', coverage: 25, breakEvenViews: 4000, remainingViews: 3000 });
});

test('traffic above break-even caps the meter and shows positive profit', () => {
    const result = estimateBlogProfitability(6000, '5', '20');

    assert.equal(result.profit, 10);
    assert.equal(result.status, 'Estimated profit');
    assert.equal(result.coverage, 100);
    assert.equal(result.remainingViews, 0);
});

test('exact cost recovery is break-even', () => {
    const result = estimateBlogProfitability(4000, '5', '20');

    assert.equal(result.status, 'Break-even');
    assert.equal(result.profit, 0);
});

test('zero RPM cannot recover positive costs', () => {
    const result = estimateBlogProfitability(1000, '0', '20');

    assert.equal(result.revenue, 0);
    assert.equal(result.breakEvenViews, null);
    assert.equal(result.remainingViews, null);
    assert.equal(result.coverage, 0);
});

test('zero costs do not produce an infinite coverage percentage', () => {
    const result = estimateBlogProfitability(1000, '5', '0');

    assert.equal(result.status, 'Estimated profit');
    assert.equal(result.coverage, null);
    assert.equal(result.breakEvenViews, 0);
    assert.equal(estimateBlogProfitability(0, '0', '0').status, 'Break-even');
});

test('no traffic produces no revenue and break-even views round up', () => {
    const result = estimateBlogProfitability(0, '3', '10');

    assert.equal(result.revenue, 0);
    assert.equal(result.profit, -10);
    assert.equal(result.breakEvenViews, 3334);
    assert.equal(result.remainingViews, 3334);
});

test('missing, negative, non-finite and out of range assumptions have no estimate', () => {
    for (const invalid of ['', ' ', undefined, null, '-1', 'abc', 'Infinity', '1000000001']) {
        assert.equal(estimateBlogProfitability(1000, invalid, '20'), null);
        assert.equal(estimateBlogProfitability(1000, '5', invalid), null);
    }
    assert.equal(estimateBlogProfitability(1000, '1000001', '20'), null);
    assert.equal(estimateBlogProfitability(-1, '5', '20'), null);
});
