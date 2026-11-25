<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\NvidiaBlogGenerator;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

#[Signature('blog:generate-ai {--count= : Maximum posts to create this run, within the daily quota}')]
#[Description('Generate and schedule the daily quota of automotive blog posts with NVIDIA AI')]
class GenerateAiBlogPosts extends Command
{
    public function handle(NvidiaBlogGenerator $generator): int
    {
        $requestedCount = $this->option('count');

        if ($requestedCount !== null && (! ctype_digit((string) $requestedCount) || (int) $requestedCount < 1)) {
            $this->error('The --count option must be a positive integer.');

            return self::INVALID;
        }

        $dailyQuota = (int) config('blog.ai.posts_per_day');
        $createdToday = $this->createdToday();
        $remainingQuota = $this->remainingQuota();

        if ($remainingQuota === 0) {
            $this->info("Daily AI blog quota already reached ({$dailyQuota}).");

            return self::SUCCESS;
        }

        $authorId = User::query()->where('is_blog_admin', true)->orderBy('id')->value('id');

        if ($authorId === null) {
            $this->error('No blog administrator exists. Grant access with blog:admin first.');

            return self::FAILURE;
        }

        $count = min($remainingQuota, $requestedCount === null ? $remainingQuota : (int) $requestedCount);
        $excludedTitles = BlogPost::query()->latest('id')->limit(50)->pluck('title')->all();
        $created = 0;

        for ($index = 0; $index < $count; $index++) {
            try {
                $article = $generator->generate($excludedTitles);
                $post = $this->storeArticle($article, $authorId, $createdToday + $index);
                $excludedTitles[] = $post->title;
                $created++;
                $this->info("Scheduled [{$post->title}] for {$post->publish_at->toDateTimeString()}.");
            } catch (Throwable $exception) {
                Log::error('AI blog generation failed', [
                    'created' => $created,
                    'message' => $exception->getMessage(),
                ]);
                $this->error($exception->getMessage());
                $this->warn("Created {$created} AI blog post(s) before generation stopped; rerun to fill the daily quota.");

                return self::FAILURE;
            }
        }

        $this->info("Created {$created} AI blog post(s).");

        return self::SUCCESS;
    }

    public function remainingQuota(): int
    {
        return max(0, (int) config('blog.ai.posts_per_day') - $this->createdToday());
    }

    private function createdToday(): int
    {
        [$dayStart, $dayEnd] = $this->dayBoundaries();

        return BlogPost::query()
            ->where('source', 'nvidia')
            ->where('created_by_automation', true)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->count();
    }

    /**
     * @param  array{title: string, excerpt: string, body_markdown: string, seo_title: string, meta_description: string, tags: array<int, string>}  $article
     */
    private function storeArticle(array $article, int $authorId, int $slot): BlogPost
    {
        return DB::transaction(function () use ($article, $authorId, $slot): BlogPost {
            $publishAt = $this->publishAt($slot);
            $post = BlogPost::create([
                'author_id' => $authorId,
                'byline' => config('blog.ai.byline'),
                'title' => $article['title'],
                'slug' => $this->uniqueSlug($article['title']),
                'excerpt' => $article['excerpt'],
                'body_markdown' => $article['body_markdown'],
                'status' => 'scheduled',
                'publish_at' => $publishAt,
                'published_at' => null,
                'seo_title' => $article['seo_title'],
                'meta_description' => $article['meta_description'],
                'source' => 'nvidia',
                'created_by_automation' => true,
            ]);

            $tagIds = collect($article['tags'])
                ->map(function (string $name): int {
                    $slug = Str::slug($name);

                    if ($slug === '') {
                        throw new RuntimeException("NVIDIA tag [{$name}] cannot be converted to a slug.");
                    }

                    return BlogTag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
                });

            $post->tags()->sync($tagIds);

            return $post;
        });
    }

    private function publishAt(int $slot): CarbonInterface
    {
        $timezone = (string) config('blog.timezone');
        $firstPublishTime = (string) config('blog.ai.first_publish_time');

        return now($timezone)
            ->setTimeFromTimeString($firstPublishTime)
            ->addHours($slot)
            ->setTimezone((string) config('app.timezone'));
    }

    private function uniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            throw new RuntimeException('NVIDIA title cannot be converted to a slug.');
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    private function dayBoundaries(): array
    {
        $timezone = (string) config('blog.timezone');
        $applicationTimezone = (string) config('app.timezone');
        $localNow = now($timezone);

        return [
            $localNow->copy()->startOfDay()->setTimezone($applicationTimezone),
            $localNow->copy()->endOfDay()->setTimezone($applicationTimezone),
        ];
    }
}
