<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogPostViewRecorder;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BlogPostViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_views_count_once_per_session_per_post_and_local_day(): void
    {
        config(['blog.timezone' => 'Asia/Manila']);
        $this->travelTo(Carbon::parse('2026-10-01 15:59:00', 'UTC'));
        $post = BlogPost::factory()->published()->create();
        $other = BlogPost::factory()->published()->create();
        $updatedAt = $post->updated_at;
        $this->withSession(['reader' => true]);
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());

        $this->get(route('blog.show', $post->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('post.views_count', 0));
        $this->get(route('blog.show', $post->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('post.views_count', 1));
        $this->get(route('blog.show', $other->slug))->assertOk();

        $this->assertSame(1, $post->fresh()->views_count);
        $this->assertSame(1, $other->fresh()->views_count);
        $this->assertDatabaseHas('blog_post_views', ['blog_post_id' => $post->id, 'viewed_on' => '2026-10-01']);
        $this->assertSame(64, strlen(DB::table('blog_post_views')->first()->session_hash));

        $this->travel(2)->minutes();
        $this->get(route('blog.show', $post->slug))->assertOk();
        $this->assertSame(2, $post->fresh()->views_count);
        $this->assertDatabaseHas('blog_post_views', ['blog_post_id' => $post->id, 'viewed_on' => '2026-10-02']);

        $this->app['session']->regenerate();
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->get(route('blog.show', $post->slug))->assertOk();
        $this->assertSame(3, $post->fresh()->views_count);
        $this->assertTrue($updatedAt->equalTo($post->fresh()->updated_at));
    }

    public function test_excluded_requests_do_not_count(): void
    {
        $post = BlogPost::factory()->published()->create();
        $draft = BlogPost::factory()->create();
        $future = BlogPost::factory()->published()->create(['published_at' => now()->addDay()]);

        $this->withHeaders(['Purpose' => 'prefetch'])->get(route('blog.show', $post->slug))->assertOk();
        $this->flushHeaders();
        $this->withHeaders(['User-Agent' => 'ExampleBot/1.0'])->get(route('blog.show', $post->slug))->assertOk();
        $this->flushHeaders();
        $this->head(route('blog.show', $post->slug))->assertOk();
        $this->get(route('blog.show', $draft->slug))->assertNotFound();
        $this->get(route('blog.show', $future->slug))->assertNotFound();
        $this->get(route('blog.show', 'missing-post'))->assertNotFound();
        $this->get(route('blog.index'))->assertOk();
        $this->get(route('blog.feed'))->assertOk();
        $this->get(route('blog.sitemap'))->assertOk();
        $this->actingAs(User::factory()->blogAdmin()->create())
            ->get(route('admin.blog.preview', $post))->assertOk();

        $this->assertDatabaseCount('blog_post_views', 0);
        $this->assertSame(0, $post->fresh()->views_count);
    }

    public function test_counts_survive_slug_changes_and_archiving_and_are_visible_to_admins(): void
    {
        $post = BlogPost::factory()->published()->create();
        $this->actingAs(User::factory()->blogAdmin()->create());
        $this->withSession(['reader' => true]);
        $this->withCookie(config('session.cookie'), $this->app['session']->getId());
        $this->get(route('blog.show', $post->slug))->assertOk();
        $post->update(['slug' => 'changed-slug']);
        $this->get(route('blog.show', $post->slug))->assertOk();
        $this->delete(route('admin.blog.destroy', $post))->assertRedirect();
        $this->get(route('blog.show', $post->slug))->assertNotFound();

        $this->get(route('admin.blog.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.data.0.views_count', 1)
                ->where('posts.data.0.status', 'archived'));
        $this->assertSame(1, $post->fresh()->views_count);
    }

    public function test_pruning_old_daily_records_preserves_totals_and_recent_deduplication(): void
    {
        config(['blog.timezone' => 'Asia/Manila']);
        $this->travelTo(Carbon::parse('2026-10-10 00:30:00', 'Asia/Manila'));
        $post = BlogPost::factory()->published()->create();
        $recorder = app(BlogPostViewRecorder::class);
        $hash = hash('sha256', 'reader');
        $recorder->record($post->id, $hash, '2026-10-02');
        $recorder->record($post->id, $hash, '2026-10-03');
        $recorder->record($post->id, $hash, '2026-10-10');

        $this->artisan('blog:prune-views')->assertSuccessful();

        $this->assertDatabaseMissing('blog_post_views', ['viewed_on' => '2026-10-02']);
        $this->assertDatabaseCount('blog_post_views', 2);
        $recorder->record($post->id, $hash, '2026-10-10');
        $this->assertSame(3, $post->fresh()->views_count);
    }

    public function test_failed_counter_update_rolls_back_the_daily_record(): void
    {
        $post = BlogPost::factory()->published()->create();
        DB::unprepared("CREATE TRIGGER reject_blog_view_update BEFORE UPDATE OF views_count ON blog_posts BEGIN SELECT RAISE(ABORT, 'counter unavailable'); END");

        try {
            app(BlogPostViewRecorder::class)->record($post->id, hash('sha256', 'reader'), '2026-10-01');
            $this->fail('Expected the counter update to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('counter unavailable', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER reject_blog_view_update');
        }

        $this->assertDatabaseCount('blog_post_views', 0);
        $this->assertSame(0, $post->fresh()->views_count);
    }

    public function test_concurrent_duplicate_views_increment_only_once(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'blog-views-');
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.blog_view_concurrency' => [
            ...config('database.connections.sqlite'),
            'database' => $database,
            'busy_timeout' => 5000,
        ]]);
        $processes = [];

        try {
            DB::setDefaultConnection('blog_view_concurrency');
            $this->artisan('migrate', ['--database' => 'blog_view_concurrency', '--force' => true])->assertSuccessful();
            $post = BlogPost::factory()->published()->create();
            $script = <<<'SCRIPT'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
    'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.busy_timeout' => 5000]);
\Illuminate\Support\Facades\DB::purge('sqlite');
echo "ready\n";
app(\App\Services\BlogPostViewRecorder::class)->record((int) $argv[2], hash('sha256', 'same-reader'), '2026-10-01');
SCRIPT;
            DB::beginTransaction();
            DB::table('blog_posts')->where('id', $post->id)->update(['views_count' => 0]);
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([PHP_BINARY, '-r', $script, $database, (string) $post->id], base_path());
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready')));
            }
            DB::commit();
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            }

            $this->assertSame(1, $post->fresh()->views_count);
            $this->assertDatabaseCount('blog_post_views', 1);
        } finally {
            if (DB::connection('blog_view_concurrency')->transactionLevel() > 0) {
                DB::connection('blog_view_concurrency')->rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::setDefaultConnection($originalConnection);
            DB::purge('blog_view_concurrency');
            unlink($database);
        }
    }
}
