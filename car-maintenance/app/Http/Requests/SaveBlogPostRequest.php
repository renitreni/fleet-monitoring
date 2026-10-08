<?php

namespace App\Http\Requests;

use App\Models\BlogPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SaveBlogPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $blogPost = $this->route('blogPost');

        return $blogPost instanceof BlogPost
            ? $this->user()->can('update', $blogPost)
            : $this->user()->can('create', BlogPost::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $blogPost = $this->route('blogPost');

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('blog_posts', 'slug')->ignore($blogPost?->id),
            ],
            'excerpt' => ['required', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
            'publish_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:180'],
            'byline' => ['nullable', 'string', 'max:120'],
            'cover_image' => [
                'nullable',
                $this->hasFile('cover_image') ? 'image' : 'string',
                $this->hasFile('cover_image') ? 'max:4096' : 'max:2048',
            ],
            'remove_cover_image' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'publish_at.required_if' => 'Choose a publication date and time for a scheduled post.',
            'cover_image.image' => 'The cover image must be an image file (JPG, PNG, or WebP).',
            'cover_image.max' => 'The cover image may not exceed 4 MB.',
            'tags.max' => 'A post can have at most 10 tags.',
        ];
    }

    /**
     * Normalize tags before validation: trim, drop empties, de-duplicate case-insensitively.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('tags')) {
            return;
        }

        $tags = collect($this->input('tags', []))
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => Str::lower($tag))
            ->values()
            ->all();

        $this->merge(['tags' => $tags]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('status') !== 'scheduled' || $validator->errors()->has('publish_at')) {
                return;
            }

            $publishAt = Carbon::createFromFormat(
                'Y-m-d\TH:i',
                (string) $this->input('publish_at'),
                config('blog.timezone'),
            );

            if ($publishAt->isPast()) {
                $validator->errors()->add('publish_at', 'The publication time must be in the future.');
            }
        }];
    }
}
