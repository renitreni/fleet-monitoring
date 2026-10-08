<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogCoverImageTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Cover story',
            'slug' => 'cover-story',
            'excerpt' => 'A post with a cover image.',
            'body_markdown' => "## Intro\n\nBody text.",
            'status' => 'draft',
            ...$overrides,
        ];
    }

    public function test_admin_can_store_a_cover_image_url(): void
    {
        $admin = User::factory()->blogAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), $this->payload([
                'cover_image' => 'https://cdn.example.com/covers/story.webp',
            ]))
            ->assertRedirect();

        $this->assertSame(
            'https://cdn.example.com/covers/story.webp',
            BlogPost::query()->firstOrFail()->cover_image
        );
    }

    public function test_admin_can_upload_a_cover_image_file(): void
    {
        Storage::fake('public');

        $admin = User::factory()->blogAdmin()->create();
        $file = UploadedFile::fake()->image('cover.webp', 1200, 600)->size(500);

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), $this->payload(['cover_image' => $file]))
            ->assertRedirect();

        $post = BlogPost::query()->firstOrFail();

        $this->assertNotNull($post->cover_image);
        $this->assertStringStartsWith('blog-covers/', $post->cover_image);
        Storage::disk('public')->assertExists($post->cover_image);
        $this->assertSame(Storage::disk('public')->url($post->cover_image), $post->coverImageUrl());
    }

    public function test_admin_can_remove_a_cover_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->blogAdmin()->create();
        $post = BlogPost::factory()->create(['cover_image' => 'blog-covers/old.webp']);
        Storage::disk('public')->put('blog-covers/old.webp', 'fake');

        $this->actingAs($admin)
            ->put(route('admin.blog.update', $post), $this->payload([
                'slug' => $post->slug,
                'remove_cover_image' => true,
            ]))
            ->assertRedirect();

        $this->assertNull($post->refresh()->cover_image);
        Storage::disk('public')->assertMissing('blog-covers/old.webp');
    }

    public function test_cover_image_must_be_an_image_when_uploaded(): void
    {
        Storage::fake('public');

        $admin = User::factory()->blogAdmin()->create();
        $file = UploadedFile::fake()->create('cover.txt', 10);

        $this->actingAs($admin)
            ->from(route('admin.blog.create'))
            ->post(route('admin.blog.store'), $this->payload(['cover_image' => $file]))
            ->assertSessionHasErrors('cover_image');
    }
}
