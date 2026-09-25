import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export default function BlogAdminIndex({ posts, createUrl }) {
    return (
        <AuthenticatedLayout
            title="Manage blog"
            header={
                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div>
                        <p className="eyebrow">Editorial workspace</p>
                        <h1 className="mt-2 text-3xl font-black uppercase">Manage blog</h1>
                        <p className="mt-3 text-sm text-[var(--text-muted)]">
                            Draft, schedule, and publish Motologic stories.
                        </p>
                    </div>
                    <Link
                        href={createUrl}
                        className="w-fit bg-[var(--accent)] px-5 py-3 text-xs font-black uppercase tracking-[0.14em] text-white"
                    >
                        New post
                    </Link>
                </div>
            }
        >
            <div className="mx-auto max-w-[1384px] px-5 py-10 sm:px-8">
                <div className="overflow-x-auto border border-[var(--border)] bg-[var(--surface)]">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b border-[var(--border)] text-[10px] font-black uppercase tracking-[0.18em] text-[var(--text-muted)]">
                            <tr>
                                <th className="px-5 py-4">Article</th>
                                <th className="px-5 py-4">Status</th>
                                <th className="px-5 py-4">Publish time</th>
                                <th className="px-5 py-4">Updated</th>
                                <th className="px-5 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border)]">
                            {posts.data.map((post) => (
                                <tr key={post.id}>
                                    <td className="px-5 py-5">
                                        <p className="font-bold">{post.title}</p>
                                        <p className="mt-1 text-xs text-[var(--text-muted)]">/{post.slug}</p>
                                    </td>
                                    <td className="px-5 py-5">
                                        <span className="border border-[var(--border)] px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em]">
                                            {post.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-5 text-[var(--text-muted)]">
                                        {formatDate(post.publish_at || post.published_at)}
                                    </td>
                                    <td className="px-5 py-5 text-[var(--text-muted)]">
                                        {formatDate(post.updated_at)}
                                    </td>
                                    <td className="px-5 py-5">
                                        <div className="flex justify-end gap-4 text-xs font-black uppercase tracking-[0.12em]">
                                            <Link href={post.preview_url}>Preview</Link>
                                            <Link href={post.edit_url} className="text-[var(--accent)]">
                                                Edit
                                            </Link>
                                            {post.public_url && <Link href={post.public_url}>View</Link>}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {posts.data.length === 0 && (
                        <p className="p-8 text-sm text-[var(--text-muted)]">No blog posts yet.</p>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
