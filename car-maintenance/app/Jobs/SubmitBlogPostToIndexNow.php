<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a blog post URL to IndexNow (Bing, Yandex, Seznam, Naver) so a
 * publish or significant update gets crawled without waiting for sitemap
 * polling. Notifications are a hint, not an indexing guarantee.
 */
class SubmitBlogPostToIndexNow implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $slug,
    ) {}

    public function handle(): void
    {
        // The test suite runs this queue synchronously; never touch the
        // network from handle() there (tests call submit() directly with
        // Http fakes to assert dispatch and payload).
        if (app()->runningUnitTests()) {
            return;
        }

        if (! $this->submit()) {
            $this->release($this->backoff);
        }
    }

    public function submit(): bool
    {
        $response = $this->send();

        if ($response->failed()) {
            Log::warning('IndexNow submission rejected', [
                'slug' => $this->slug,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    protected function send(): Response
    {
        $key = (string) config('services.indexnow.key');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return Http::timeout(15)->post((string) config('services.indexnow.endpoint'), [
            'host' => $host,
            'key' => $key,
            'keyLocation' => "https://{$host}/{$key}.txt",
            'urlList' => [route('blog.show', $this->slug)],
        ]);
    }
}
