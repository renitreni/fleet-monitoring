import { useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import Label from '@/Components/Label';
import TextInput from '@/Components/TextInput';

const emptyPost = {
    title: '',
    slug: '',
    excerpt: '',
    body_markdown: '',
    status: 'draft',
    publish_at: '',
    seo_title: '',
    meta_description: '',
    byline: '',
    tags: '',
    cover_image: null,
    remove_cover_image: false,
};

function parseTags(value) {
    return value
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean);
}

export default function BlogAdminEdit({ post, submitUrl, timezone }) {
    const initial = post ? { ...emptyPost, ...post, cover_image: null, remove_cover_image: false } : emptyPost;
    const form = useForm(initial);
    const [coverPreview, setCoverPreview] = useState(post?.cover_image_url ?? null);
    const fileInputRef = useRef(null);

    function submit(event) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
        };

        form.transform((data) => ({
            ...data,
            tags: parseTags(data.tags ?? ''),
        }));

        if (post) form.put(submitUrl, options);
        else form.post(submitUrl, options);
    }

    function handleCoverChange(event) {
        const file = event.target.files?.[0] ?? null;
        form.setData('cover_image', file);
        form.setData('remove_cover_image', false);

        if (coverPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(coverPreview);
        }
        setCoverPreview(file ? URL.createObjectURL(file) : post?.cover_image_url ?? null);
    }

    function handleRemoveCover() {
        form.setData('cover_image', null);
        form.setData('remove_cover_image', true);
        if (fileInputRef.current) fileInputRef.current.value = '';
        if (coverPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(coverPreview);
        }
        setCoverPreview(null);
    }

    return (
        <AuthenticatedLayout
            title={post ? `Edit ${post.title}` : 'New blog post'}
            header={
                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div>
                        <p className="eyebrow">Editorial workspace</p>
                        <h1 className="mt-2 text-3xl font-black uppercase">{post ? 'Edit post' : 'New post'}</h1>
                    </div>
                    <div className="flex gap-4 text-xs font-black uppercase tracking-[0.12em]">
                        <Link href="/admin/blog">All posts</Link>
                        {post?.preview_url && (
                            <Link href={post.preview_url} className="text-[var(--accent)]">
                                Preview
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <form
                onSubmit={submit}
                encType="multipart/form-data"
                className="mx-auto grid max-w-[1384px] gap-8 px-5 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_340px]"
            >
                <div className="space-y-6">
                    <div>
                        <Label htmlFor="title">Title</Label>
                        <TextInput
                            id="title"
                            required
                            maxLength={160}
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            className="border p-3 text-lg font-bold"
                        />
                        {form.errors.title && <p className="mt-2 text-sm text-red-600">{form.errors.title}</p>}
                    </div>
                    <div>
                        <Label htmlFor="slug">URL slug</Label>
                        <TextInput
                            id="slug"
                            maxLength={180}
                            value={form.data.slug}
                            onChange={(event) => form.setData('slug', event.target.value.toLowerCase())}
                            placeholder="Generated from the title when left blank"
                            className="border p-3 font-mono text-sm"
                        />
                        {form.errors.slug && <p className="mt-2 text-sm text-red-600">{form.errors.slug}</p>}
                    </div>
                    <div>
                        <Label htmlFor="excerpt">Excerpt</Label>
                        <textarea
                            id="excerpt"
                            required
                            maxLength={500}
                            value={form.data.excerpt}
                            onChange={(event) => form.setData('excerpt', event.target.value)}
                            className="mt-1 min-h-28 w-full border border-[var(--border)] bg-[var(--surface)] p-3"
                        />
                        <p className="mt-1 text-xs text-[var(--text-muted)]">{form.data.excerpt.length}/500</p>
                        {form.errors.excerpt && <p className="mt-2 text-sm text-red-600">{form.errors.excerpt}</p>}
                    </div>
                    <div>
                        <Label htmlFor="body_markdown">Article body (Markdown)</Label>
                        <textarea
                            id="body_markdown"
                            required
                            value={form.data.body_markdown}
                            onChange={(event) => form.setData('body_markdown', event.target.value)}
                            className="mt-1 min-h-[560px] w-full border border-[var(--border)] bg-[var(--surface)] p-4 font-mono text-sm leading-7"
                            placeholder={'## Start with a useful heading\n\nWrite the article here.'}
                        />
                        {form.errors.body_markdown && (
                            <p className="mt-2 text-sm text-red-600">{form.errors.body_markdown}</p>
                        )}
                        {post?.reading_time != null && (
                            <p className="mt-1 text-xs text-[var(--text-muted)]">
                                Reading time: {post.reading_time} min (updated when the body changes)
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="cover_image">Cover image</Label>
                        {coverPreview && (
                            <img
                                src={coverPreview}
                                alt="Cover preview"
                                className="mt-2 aspect-[2/1] w-full max-w-md border border-[var(--border)] object-cover"
                            />
                        )}
                        <input
                            ref={fileInputRef}
                            id="cover_image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={handleCoverChange}
                            className="mt-2 block w-full text-sm"
                        />
                        <div className="mt-2 flex items-center gap-4">
                            {(coverPreview || post?.cover_image_url) && (
                                <button
                                    type="button"
                                    onClick={handleRemoveCover}
                                    className="text-xs font-black uppercase tracking-[0.12em] text-red-600"
                                >
                                    Remove cover
                                </button>
                            )}
                        </div>
                        <p className="mt-1 text-xs text-[var(--text-muted)]">
                            JPG, PNG, or WebP up to 4 MB. Shown as the article hero and social share image.
                        </p>
                        {form.errors.cover_image && <p className="mt-2 text-sm text-red-600">{form.errors.cover_image}</p>}
                    </div>
                </div>

                <aside className="space-y-6">
                    <div className="space-y-5 border border-[var(--border)] bg-[var(--surface)] p-5">
                        <h2 className="text-sm font-black uppercase tracking-[0.14em]">Publication</h2>
                        <div>
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                value={form.data.status}
                                onChange={(event) => form.setData('status', event.target.value)}
                                className="mt-1 w-full border border-[var(--border)] bg-[var(--surface)] p-3"
                            >
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="published">Publish now</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        {form.data.status === 'scheduled' && (
                            <div>
                                <Label htmlFor="publish_at">Publish date and time</Label>
                                <TextInput
                                    id="publish_at"
                                    type="datetime-local"
                                    required
                                    value={form.data.publish_at || ''}
                                    onChange={(event) => form.setData('publish_at', event.target.value)}
                                    className="border p-3"
                                />
                                <p className="mt-2 text-xs text-[var(--text-muted)]">Timezone: {timezone}</p>
                                {form.errors.publish_at && (
                                    <p className="mt-2 text-sm text-red-600">{form.errors.publish_at}</p>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="space-y-5 border border-[var(--border)] bg-[var(--surface)] p-5">
                        <h2 className="text-sm font-black uppercase tracking-[0.14em]">Organization</h2>
                        <div>
                            <Label htmlFor="byline">Byline</Label>
                            <TextInput
                                id="byline"
                                maxLength={120}
                                value={form.data.byline || ''}
                                onChange={(event) => form.setData('byline', event.target.value)}
                                placeholder="Motologic Editorial Team"
                                className="border p-3"
                            />
                            <p className="mt-1 text-xs text-[var(--text-muted)]">
                                Defaults to the site name. A real byline (e.g. “Motologic Editorial Team”) helps search
                                trust signals.
                            </p>
                            {form.errors.byline && <p className="mt-2 text-sm text-red-600">{form.errors.byline}</p>}
                        </div>
                        <div>
                            <Label htmlFor="tags">Tags</Label>
                            <TextInput
                                id="tags"
                                value={form.data.tags || ''}
                                onChange={(event) => form.setData('tags', event.target.value)}
                                placeholder="maintenance, pms, toyota"
                                className="border p-3"
                            />
                            <p className="mt-1 text-xs text-[var(--text-muted)]">Comma-separated, up to 10.</p>
                            {form.errors.tags && <p className="mt-2 text-sm text-red-600">{form.errors.tags}</p>}
                        </div>
                    </div>

                    <div className="space-y-5 border border-[var(--border)] bg-[var(--surface)] p-5">
                        <h2 className="text-sm font-black uppercase tracking-[0.14em]">Search preview</h2>
                        <div>
                            <Label htmlFor="seo_title">SEO title</Label>
                            <TextInput
                                id="seo_title"
                                maxLength={70}
                                value={form.data.seo_title || ''}
                                onChange={(event) => form.setData('seo_title', event.target.value)}
                                className="border p-3"
                            />
                            <p className="mt-1 text-xs text-[var(--text-muted)]">
                                {(form.data.seo_title || form.data.title || '').length}/70
                                {(form.data.seo_title || form.data.title || '').length > 60 && ' — may truncate in search results'}
                            </p>
                        </div>
                        <div>
                            <Label htmlFor="meta_description">Meta description</Label>
                            <textarea
                                id="meta_description"
                                maxLength={180}
                                value={form.data.meta_description || ''}
                                onChange={(event) => form.setData('meta_description', event.target.value)}
                                className="mt-1 min-h-28 w-full border border-[var(--border)] bg-[var(--surface)] p-3"
                            />
                            <p className="mt-1 text-xs text-[var(--text-muted)]">
                                {(form.data.meta_description || '').length}/180
                            </p>
                        </div>
                    </div>

                    {Object.keys(form.errors).length > 0 && (
                        <p role="alert" className="text-sm text-red-600">
                            Please correct the highlighted fields.
                        </p>
                    )}
                    <Button processing={form.processing} className="w-full justify-center py-3">
                        {form.processing ? 'Saving…' : 'Save post'}
                    </Button>
                </aside>
            </form>
        </AuthenticatedLayout>
    );
}
