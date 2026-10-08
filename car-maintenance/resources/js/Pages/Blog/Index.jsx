import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BlogPostCard from '@/Components/BlogPostCard';
import Pagination from '@/Components/Pagination';

const DEFAULT_HEADING = {
    eyebrow: 'Motologic journal',
    title: 'Drive smarter.',
    description: 'Practical maintenance guides, ownership advice, and ideas for the road ahead.',
};

const DEFAULT_DESCRIPTION =
    'Practical car maintenance, vehicle ownership, and road-trip guidance from Motologic.';

export default function BlogIndex({
    posts,
    canonicalUrl,
    pageTitle = 'Blog',
    noindex = false,
    searchQuery = '',
    heading = null,
}) {
    const hero = !searchQuery && posts.current_page === 1 ? posts.data[0] : null;
    const gridPosts = hero ? posts.data.slice(1) : posts.data;
    const heroIndex = posts.from;

    return (
        <AuthenticatedLayout
            title={pageTitle}
            header={
                <div className="max-w-3xl">
                    <p className="eyebrow">{heading?.eyebrow ?? DEFAULT_HEADING.eyebrow}</p>
                    <h1 className="mt-3 text-4xl font-black uppercase tracking-[-0.04em] sm:text-6xl">
                        {heading?.title ?? DEFAULT_HEADING.title}
                    </h1>
                    <p className="mt-4 text-base leading-7 text-[var(--text-muted)]">
                        {heading?.description ?? DEFAULT_HEADING.description}
                    </p>
                    <form action="/blog" method="get" className="mt-6 flex max-w-md gap-2" role="search">
                        <input
                            type="search"
                            name="q"
                            defaultValue={searchQuery}
                            placeholder="Search articles…"
                            aria-label="Search articles"
                            className="w-full border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
                        />
                        <button
                            type="submit"
                            className="bg-[var(--accent)] px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-white"
                        >
                            Search
                        </button>
                    </form>
                </div>
            }
        >
            <Head>
                <meta name="description" content={heading?.description ?? DEFAULT_DESCRIPTION} />
                <link rel="canonical" href={canonicalUrl} />
                {noindex && <meta name="robots" content="noindex,follow" />}
                <link rel="alternate" type="application/rss+xml" title="Motologic Blog" href="/blog/feed.xml" />
            </Head>

            <div className="mx-auto max-w-[1384px] px-5 py-12 sm:px-8 lg:py-20">
                {posts.data.length === 0 ? (
                    <div className="border border-[var(--border)] bg-[var(--surface)] p-10">
                        {searchQuery ? (
                            <>
                                <p className="eyebrow">No results</p>
                                <h2 className="mt-4 text-2xl font-black uppercase">
                                    Nothing matches “{searchQuery}”.
                                </h2>
                                <p className="mt-3 text-[var(--text-muted)]">
                                    Try a different keyword, or browse the latest articles.
                                </p>
                                <Link href="/blog" className="mt-4 inline-block text-[var(--accent)]">
                                    Back to the latest articles →
                                </Link>
                            </>
                        ) : posts.total === 0 ? (
                            <>
                                <p className="eyebrow">Coming soon</p>
                                <h2 className="mt-4 text-2xl font-black uppercase">
                                    The first story is in the garage.
                                </h2>
                                <p className="mt-3 text-[var(--text-muted)]">
                                    Check back soon for practical motoring advice.
                                </p>
                            </>
                        ) : (
                            <>
                                <h2 className="text-2xl font-black uppercase">No articles on this page.</h2>
                                <Link href={posts.first_page_url} className="mt-4 inline-block text-[var(--accent)]">
                                    Back to the latest articles →
                                </Link>
                            </>
                        )}
                    </div>
                ) : (
                    <>
                        {hero && (
                            <article className="group mb-px grid bg-[var(--surface)] md:grid-cols-2">
                                <Link
                                    href={hero.url}
                                    className="block overflow-hidden border border-[var(--border)]"
                                    tabIndex={-1}
                                >
                                    {hero.cover_image_url ? (
                                        <img
                                            src={hero.cover_image_url}
                                            alt=""
                                            className="h-full min-h-64 w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                                        />
                                    ) : (
                                        <div className="flex h-full min-h-64 items-center justify-center border border-[var(--border)] bg-[var(--background)] p-10">
                                            <span className="text-6xl font-black uppercase tracking-[-0.05em] text-[var(--text-muted)]">
                                                /{String(heroIndex).padStart(2, '0')}
                                            </span>
                                        </div>
                                    )}
                                </Link>
                                <div className="flex flex-col justify-between p-7 sm:p-10">
                                    <div>
                                        <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--text-muted)]">
                                            Latest — {hero.reading_time} min read
                                        </p>
                                        <h2 className="mt-6 text-3xl font-black uppercase leading-[0.95] tracking-[-0.04em] sm:text-5xl">
                                            <Link href={hero.url} className="transition group-hover:text-[var(--accent)]">
                                                {hero.title}
                                            </Link>
                                        </h2>
                                        <p className="mt-5 text-sm leading-7 text-[var(--text-muted)]">{hero.excerpt}</p>
                                    </div>
                                    <div className="mt-8 border-t border-[var(--border)] pt-5 text-xs text-[var(--text-muted)]">
                                        By {hero.author_name}
                                    </div>
                                </div>
                            </article>
                        )}
                        <div className="grid gap-px bg-[var(--border)] md:grid-cols-2 xl:grid-cols-3">
                            {gridPosts.map((post, index) => (
                                <BlogPostCard
                                    key={post.slug}
                                    post={post}
                                    index={(hero ? heroIndex + 1 : posts.from) + index}
                                />
                            ))}
                        </div>
                    </>
                )}

                <Pagination pagination={posts} ariaLabel="Blog pagination" />
            </div>
        </AuthenticatedLayout>
    );
}
