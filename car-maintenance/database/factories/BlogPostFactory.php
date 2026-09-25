<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(),
            'body_markdown' => "## What to know\n\n".fake()->paragraphs(4, true),
            'status' => 'draft',
            'publish_at' => null,
            'published_at' => null,
            'seo_title' => null,
            'meta_description' => null,
            'source' => 'admin',
            'created_by_automation' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'publish_at' => now()->subMinute(),
            'published_at' => now()->subMinute(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'scheduled',
            'publish_at' => now()->addDay(),
            'published_at' => null,
        ]);
    }
}
