import { Link } from '@inertiajs/react';

function formatDate(value) {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'long', timeZone: 'Asia/Manila' }).format(new Date(value));
}

export default function BlogPostCard({ post, index, showTags = true }) {
    return (
        <article className="group flex min-h-80 flex-col bg-[var(--surface)] p-7 sm:p-9">
            <div className="flex items-center justify-between text-[10px] font-black uppercase tracking-[0.18em] text-[var(--text-muted)]">
                <span>{index != null ? `/${String(index).padStart(2, '0')}` : '—'}</span>
                <span>{post.reading_time} min read</span>
            </div>
            {post.cover_image_url && (
                <Link href={post.url} className="mt-6 block overflow-hidden border border-[var(--border)]" tabIndex={-1}>
                    <img
                        src={post.cover_image_url}
                        alt=""
                        loading="lazy"
                        className="aspect-[2/1] w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                    />
                </Link>
            )}
            <h2 className={`text-3xl font-black uppercase leading-[0.95] tracking-[-0.04em] ${post.cover_image_url ? 'mt-6' : 'mt-12'}`}>
                <Link href={post.url} className="transition group-hover:text-[var(--accent)]">
                    {post.title}
                </Link>
            </h2>
            <p className="mt-5 flex-1 text-sm leading-7 text-[var(--text-muted)]">{post.excerpt}</p>
            {showTags && post.tags?.length > 0 && (
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
            <div className="mt-8 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-[var(--border)] pt-5 text-xs">
                <span className="break-all text-[var(--text-muted)]">By {post.author_name}</span>
                <time dateTime={post.published_at} className="text-[var(--text-muted)]">
                    {formatDate(post.published_at)}
                </time>
            </div>
        </article>
    );
}
