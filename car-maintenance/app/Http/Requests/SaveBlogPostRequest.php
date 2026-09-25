<?php

namespace App\Http\Requests;

use App\Models\BlogPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'publish_at.required_if' => 'Choose a publication date and time for a scheduled post.',
        ];
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
