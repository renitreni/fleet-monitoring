import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(value));
}

export default function BlogShow({ post, preview = false }) {
    return (
        <AuthenticatedLayout title={post.seo_title}>
            <Head>
                <meta name="description" content={post.meta_description} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={post.seo_title} />
                <meta property="og:description" content={post.meta_description} />
                <link rel="canonical" href={post.url} />
                {preview && <meta name="robots" content="noindex,nofollow" />}
            </Head>

            {preview && (
                <div className="border-b border-amber-500/30 bg-amber-500/10 px-5 py-3 text-center text-xs font-black uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">
                    Private preview — this page is not public
                </div>
            )}

            <article>
                <header className="border-b border-[var(--border)] px-5 py-16 sm:px-8 lg:py-24">
                    <div className="mx-auto max-w-4xl">
                        <Link href={preview ? '/admin/blog' : '/blog'} className="eyebrow w-fit">
                            {preview ? 'Back to editor' : 'Motologic journal'}
                        </Link>
                        <h1 className="mt-8 text-5xl font-black uppercase leading-[0.88] tracking-[-0.055em] sm:text-7xl lg:text-8xl">
                            {post.title}
                        </h1>
                        <p className="mt-8 max-w-3xl text-xl leading-8 text-[var(--text-muted)]">{post.excerpt}</p>
                        <div className="mt-10 flex flex-wrap gap-x-6 gap-y-2 border-t border-[var(--border)] pt-6 text-xs font-bold uppercase tracking-[0.12em] text-[var(--text-muted)]">
                            <span>{post.author}</span>
                            <time dateTime={post.published_at}>{formatDate(post.published_at)}</time>
                            <span>{post.reading_time} min read</span>
                        </div>
                    </div>
                </header>

                <div className="mx-auto max-w-3xl px-5 py-14 sm:px-8 lg:py-20">
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
            </article>
        </AuthenticatedLayout>
    );
}
