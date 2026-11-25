<?php

namespace Tests\Feature;

use App\Services\NvidiaBlogGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class NvidiaBlogGeneratorTest extends TestCase
{
    public function test_generates_a_validated_article_with_the_nvidia_request_options(): void
    {
        Http::preventStrayRequests();
        config(['blog.ai.api_key' => 'test-nvidia-key']);
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->validArticle())]],
                ],
            ]),
        ]);

        $article = app(NvidiaBlogGenerator::class)->generate(['An existing article']);

        $this->assertSame($this->validArticle(), $article);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://integrate.api.nvidia.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-nvidia-key')
                && $request['model'] === 'nvidia/nemotron-3.5-super-vl-120b-a12b'
                && $request['temperature'] === 1
                && $request['top_p'] === 0.95
                && $request['max_tokens'] === 16384
                && $request['seed'] === 42
                && $request['stream'] === false
                && $request['chat_template_kwargs'] === ['enable_thinking' => true]
                && str_contains($request['messages'][0]['content'], 'An existing article');
        });
    }

    public function test_rejects_an_api_error_response(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        config(['blog.ai.api_key' => 'test-nvidia-key']);
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => Http::response(['error' => 'Unavailable'], 503),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NVIDIA API request failed with status 503');

        app(NvidiaBlogGenerator::class)->generate();
    }

    public function test_retries_an_empty_article_and_succeeds(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        config(['blog.ai.api_key' => 'test-nvidia-key', 'blog.ai.attempts' => 3]);
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']]])
                ->push(['choices' => [['message' => ['content' => json_encode($this->validArticle())]]]]),
        ]);

        $article = app(NvidiaBlogGenerator::class)->generate();

        $this->assertSame($this->validArticle(), $article);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_reports_the_cause_when_every_attempt_returns_an_empty_article(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        config(['blog.ai.api_key' => 'test-nvidia-key', 'blog.ai.attempts' => 2]);
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => null], 'finish_reason' => 'length']],
                'usage' => ['completion_tokens' => 16384],
            ]),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('failed after 2 attempt(s). attempt 1: NVIDIA API returned an empty article (finish_reason: length, completion_tokens: 16384');

        try {
            app(NvidiaBlogGenerator::class)->generate();
        } finally {
            Http::assertSentCount(2);
        }
    }

    public function test_rejects_invalid_article_json(): void
    {
        Http::preventStrayRequests();
        Sleep::fake();
        config(['blog.ai.api_key' => 'test-nvidia-key']);
        Http::fake([
            'integrate.api.nvidia.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{not-json}']],
                ],
            ]),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NVIDIA API returned invalid JSON');

        app(NvidiaBlogGenerator::class)->generate();
    }

    public function test_rejects_generation_when_the_api_key_is_missing(): void
    {
        config(['blog.ai.api_key' => null]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NVIDIA API key is not configured. Set NVIDIA_API_KEY.');

        app(NvidiaBlogGenerator::class)->generate();
    }

    /** @return array{title: string, excerpt: string, body_markdown: string, seo_title: string, meta_description: string, tags: array<int, string>} */
    private function validArticle(): array
    {
        return [
            'title' => 'A Practical Guide to Checking Engine Oil',
            'excerpt' => 'Learn how to check engine oil safely and recognize when your vehicle needs attention.',
            'body_markdown' => '## Why oil checks matter'.PHP_EOL.rtrim(str_repeat('Useful maintenance guidance for vehicle owners. ', 20)),
            'seo_title' => 'How to Check Engine Oil Safely',
            'meta_description' => 'Follow practical steps to check engine oil, understand the readings, and spot warning signs before small problems become repairs.',
            'tags' => ['Engine Oil', 'Maintenance'],
        ];
    }
}
