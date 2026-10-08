import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BlogPostCard from '@/Components/BlogPostCard';
import SchemaManager from '@/Components/SchemaManager';

function formatDate(value) {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'long', timeZone: 'Asia/Manila' }).format(new Date(value));
}

export default function BlogShow({ post, related = [], preview = false }) {
    return (
        <AuthenticatedLayout title={post.seo_title}>
            <Head>
                <meta name="description" content={post.meta_description} />
                <meta name="author" content={post.author_name} />
                <meta property="og:type" content="article" />
                <meta property="og:url" content={post.url} />
                <meta property="og:title" content={post.seo_title} />
                <meta property="og:description" content={post.meta_description} />
                {post.cover_image_url && <meta property="og:image" content={post.cover_image_url} />}
                <meta name="twitter:card" content={post.cover_image_url ? 'summary_large_image' : 'summary'} />
                <meta name="twitter:title" content={post.seo_title} />
                <meta name="twitter:description" content={post.meta_description} />
                {post.cover_image_url && <meta name="twitter:image" content={post.cover_image_url} />}
                <meta property="article:published_time" content={post.published_at} />
                <link rel="canonical" href={post.url} />
                {preview && <meta name="robots" content="noindex,nofollow" />}
            </Head>
            <SchemaManager />

            {preview && (
                <div className="border-b border-amber-500/30 bg-amber-500/10 px-5 py-3 text-center text-xs font-black uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">
                    Private preview — this page is not public
                </div>
            )}

            <article>
                <header className="border-b border-[var(--border)] px-5 py-16 sm:px-8 lg:py-24">
                    <div className="mx-auto max-w-4xl">
                        <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-[var(--text-muted)]">
                            <Link href="/" className="transition hover:text-[var(--accent)]">
                                Home
                            </Link>
                            <span aria-hidden="true">/</span>
                            <Link href={preview ? '/admin/blog' : '/blog'} className="transition hover:text-[var(--accent)]">
                                {preview ? 'Manage blog' : 'Blog'}
                            </Link>
                            <span aria-hidden="true">/</span>
                            <span className="text-[var(--accent)]">{post.title}</span>
                        </nav>
                        <Link href={preview ? '/admin/blog' : '/blog'} className="eyebrow mt-8 w-fit">
                            {preview ? 'Back to editor' : 'Motologic journal'}
                        </Link>
                        <h1 className="mt-8 text-5xl font-black uppercase leading-[0.88] tracking-[-0.055em] sm:text-7xl lg:text-8xl">
                            {post.title}
                        </h1>
                        <p className="mt-8 max-w-3xl text-xl leading-8 text-[var(--text-muted)]">{post.excerpt}</p>
                        <div className="mt-10 flex flex-wrap gap-x-6 gap-y-2 border-t border-[var(--border)] pt-6 text-xs font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">
                            <span>By {post.author_name}</span>
                            <time dateTime={post.published_at}>{formatDate(post.published_at)}</time>
                            <span>{post.reading_time} min read</span>
                            {!preview && (
                                <span title="Counted once per browser session per day.">
                                    {new Intl.NumberFormat('en-PH').format(post.views_count)} {post.views_count === 1 ? 'view' : 'views'}
                                </span>
                            )}
                        </div>
                        {post.tags?.length > 0 && (
                            <div className="mt-4 flex flex-wrap gap-2">
                                {post.tags.map((tag) => (
                                    <Link
                                        key={tag.slug}
                                        href={tag.url}
                                        className="border border-[var(--border)] px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-[var(--text-muted)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
                                    >
                                        {tag.name}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </header>

                {post.cover_image_url && (
                    <div className="mx-auto max-w-5xl px-5 pt-14 sm:px-8">
                        <img
                            src={post.cover_image_url}
                            alt=""
                            className="aspect-[2/1] w-full border border-[var(--border)] object-cover"
                        />
                    </div>
                )}

                <div className="mx-auto flex max-w-5xl gap-12 px-5 py-14 sm:px-8 lg:py-20">
                    {post.toc?.length > 1 && (
                        <aside className="hidden w-64 shrink-0 lg:block">
                            <nav aria-label="Table of contents" className="sticky top-8 border border-[var(--border)] bg-[var(--surface)] p-5">
                                <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[var(--text-muted)]">
                                    In this article
                                </p>
                                <ol className="mt-4 space-y-2 text-sm">
                                    {post.toc.map((item) => (
                                        <li key={item.id} className={item.level === 3 ? 'pl-4' : ''}>
                                            <a href={`#${item.id}`} className="text-[var(--text-muted)] transition hover:text-[var(--accent)]">
                                                {item.text}
                                            </a>
                                        </li>
                                    ))}
                                </ol>
                            </nav>
                        </aside>
                    )}
                    <div className="min-w-0 max-w-3xl flex-1">
                        <div className="blog-content" dangerouslySetInnerHTML={{ __html: post.body_html }} />
                        <div className="mt-16 border-t border-[var(--border)] pt-8">
                            <Link
                                href={preview ? '/admin/blog' : '/blog'}
                                className="text-xs font-black uppercase tracking-[0.16em] text-[var(--accent)]"
                            >
                                ← {preview ? 'Manage blog' : 'All articles'}
                            </Link>
                        </div>
                    </div>
                </div>
            </article>

            {!preview && related.length > 0 && (
                <section className="border-t border-[var(--border)] px-5 py-14 sm:px-8" aria-label="Read next">
                    <div className="mx-auto max-w-[1384px]">
                        <p className="eyebrow">Read next</p>
                        <div className="mt-8 grid gap-px bg-[var(--border)] md:grid-cols-3">
                            {related.map((item, index) => (
                                <BlogPostCard key={item.slug} post={item} index={index + 1} />
                            ))}
                        </div>
                    </div>
                </section>
            )}
        </AuthenticatedLayout>
    );
}
