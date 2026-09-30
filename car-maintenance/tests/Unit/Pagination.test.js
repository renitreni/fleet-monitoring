import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

let server;
let Pagination;

before(async () => {
    server = await createServer({
        configFile: false,
        plugins: [react()],
        server: { middlewareMode: true, hmr: false },
        appType: 'custom',
    });
    ({ default: Pagination } = await server.ssrLoadModule('/resources/js/Components/Pagination.jsx'));
});

after(async () => {
    await server?.close();
});

function renderPagination(pagination) {
    return renderToStaticMarkup(React.createElement(Pagination, { pagination, ariaLabel: 'Blog pagination' }));
}

test('a blog with no articles has no pagination', () => {
    assert.equal(renderPagination({ total: 0, links: [] }), '');
});

test('five articles still show their total and the current page', () => {
    const html = renderPagination({
        from: 1,
        to: 5,
        total: 5,
        links: [
            { label: '&laquo; Previous', url: null, active: false },
            { label: '1', url: '/blog?page=1', active: true },
            { label: 'Next &raquo;', url: null, active: false },
        ],
    });

    assert.match(html, /aria-label="Blog pagination"/);
    assert.match(html, /Showing 1–5 of 5 articles/);
    assert.match(html, /aria-current="page"[^>]*>1<\/span>/);
    assert.equal((html.match(/aria-disabled="true"/g) || []).length, 2);
    assert.doesNotMatch(html, /<a\b/);
});

test('middle pages link to newer and older articles and mark the current page', () => {
    const html = renderPagination({
        from: 10,
        to: 18,
        total: 19,
        links: [
            { label: '&laquo; Previous', url: '/blog?page=1', active: false },
            { label: '1', url: '/blog?page=1', active: false },
            { label: '2', url: '/blog?page=2', active: true },
            { label: '3', url: '/blog?page=3', active: false },
            { label: 'Next &raquo;', url: '/blog?page=3', active: false },
        ],
    });

    assert.match(html, /Showing 10–18 of 19 articles/);
    assert.match(html, /href="\/blog\?page=1"[^>]*>&laquo; Previous<\/a>/);
    assert.match(html, /href="\/blog\?page=3"[^>]*>Next &raquo;<\/a>/);
    assert.match(html, /aria-current="page"[^>]*>2<\/span>/);
    assert.doesNotMatch(html, /href="\/blog\?page=2"/);
});

test('the final page disables Next and retains the link to newer articles', () => {
    const html = renderPagination({
        from: 19,
        to: 19,
        total: 19,
        links: [
            { label: '&laquo; Previous', url: '/blog?page=2', active: false },
            { label: '3', url: '/blog?page=3', active: true },
            { label: 'Next &raquo;', url: null, active: false },
        ],
    });

    assert.match(html, /Showing 19–19 of 19 articles/);
    assert.match(html, /href="\/blog\?page=2"[^>]*>&laquo; Previous<\/a>/);
    assert.match(html, /aria-disabled="true"[^>]*>Next &raquo;<\/span>/);
    assert.doesNotMatch(html, /href="#"/);
});

test('an out of range page keeps the archive count without showing null ranges', () => {
    const html = renderPagination({ from: null, to: null, total: 19, links: [] });

    assert.match(html, /19 articles/);
    assert.doesNotMatch(html, /Showing|null/);
});
