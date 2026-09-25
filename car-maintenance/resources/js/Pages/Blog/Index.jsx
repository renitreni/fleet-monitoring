import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(value));
}

export default function BlogIndex({ posts }) {
    return (
        <AuthenticatedLayout
            title="Blog"
            header={
                <div className="max-w-3xl">
                    <p className="eyebrow">Motologic journal</p>
                    <h1 className="mt-3 text-4xl font-black uppercase tracking-[-0.04em] sm:text-6xl">
                        Drive smarter.
                    </h1>
                    <p className="mt-4 text-base leading-7 text-[var(--text-muted)]">
                        Practical maintenance guides, ownership advice, and ideas for the road ahead.
                    </p>
                </div>
            }
        >
            <Head>
                <meta
                    name="description"
                    content="Practical car maintenance, vehicle ownership, and road-trip guidance from Motologic."
                />
                <link rel="alternate" type="application/rss+xml" title="Motologic Blog" href="/blog/feed.xml" />
            </Head>

            <div className="mx-auto max-w-[1384px] px-5 py-12 sm:px-8 lg:py-20">
                {posts.data.length === 0 ? (
                    <div className="border border-[var(--border)] bg-[var(--surface)] p-10">
                        <p className="eyebrow">Coming soon</p>
                        <h2 className="mt-4 text-2xl font-black uppercase">The first story is in the garage.</h2>
                        <p className="mt-3 text-[var(--text-muted)]">Check back soon for practical motoring advice.</p>
                    </div>
                ) : (
                    <div className="grid gap-px bg-[var(--border)] md:grid-cols-2 xl:grid-cols-3">
                        {posts.data.map((post, index) => (
                            <article
                                key={post.slug}
                                className="group flex min-h-80 flex-col bg-[var(--surface)] p-7 sm:p-9"
                            >
                                <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.18em] text-[var(--text-muted)]">
                                    <span>/{String(index + 1).padStart(2, '0')}</span>
                                    <span>{post.reading_time} min read</span>
                                </div>
                                <h2 className="mt-12 text-3xl font-black uppercase leading-[0.95] tracking-[-0.04em]">
                                    <Link href={post.url} className="transition group-hover:text-[var(--accent)]">
                                        {post.title}
                                    </Link>
                                </h2>
                                <p className="mt-5 flex-1 text-sm leading-7 text-[var(--text-muted)]">{post.excerpt}</p>
                                <div className="mt-8 flex items-center justify-between border-t border-[var(--border)] pt-5 text-xs">
                                    <span className="font-bold">{post.author}</span>
                                    <time dateTime={post.published_at} className="text-[var(--text-muted)]">
                                        {formatDate(post.published_at)}
                                    </time>
                                </div>
                            </article>
                        ))}
                    </div>
                )}

                {posts.links.length > 3 && (
                    <nav aria-label="Blog pagination" className="mt-10 flex flex-wrap gap-2">
                        {posts.links.map((link) => (
                            <Link
                                key={link.label}
                                href={link.url || '#'}
                                preserveScroll
                                className={`border px-4 py-2 text-xs font-black uppercase ${
                                    link.active
                                        ? 'border-[var(--accent)] bg-[var(--accent)] text-white'
                                        : 'border-[var(--border)] bg-[var(--surface)] text-[var(--text)]'
                                } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
