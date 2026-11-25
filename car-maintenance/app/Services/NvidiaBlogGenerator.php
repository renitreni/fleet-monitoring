<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class NvidiaBlogGenerator
{
    /**
     * Generate a validated, original automotive article.
     *
     * @param  array<int, string>  $excludedTitles
     * @return array{title: string, excerpt: string, body_markdown: string, seo_title: string, meta_description: string, tags: array<int, string>}
     */
    public function generate(array $excludedTitles = []): array
    {
        $apiKey = config('blog.ai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('NVIDIA API key is not configured. Set NVIDIA_API_KEY.');
        }

        $attempts = max(1, (int) config('blog.ai.attempts'));
        $failures = [];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->attemptGeneration($apiKey, $excludedTitles);
            } catch (RuntimeException $exception) {
                $failures[] = "attempt {$attempt}: {$exception->getMessage()}";

                if ($attempt < $attempts) {
                    Sleep::for((int) config('blog.ai.retry_delay'))->seconds();
                }
            }
        }

        throw new RuntimeException(
            "NVIDIA article generation failed after {$attempts} attempt(s). ".implode(' | ', $failures),
            previous: $exception,
        );
    }

    /**
     * @param  array<int, string>  $excludedTitles
     * @return array{title: string, excerpt: string, body_markdown: string, seo_title: string, meta_description: string, tags: array<int, string>}
     */
    private function attemptGeneration(string $apiKey, array $excludedTitles): array
    {
        try {
            $response = $this->requestArticle($apiKey, $excludedTitles);
        } catch (ConnectionException $exception) {
            throw new RuntimeException("NVIDIA API connection failed: {$exception->getMessage()}", previous: $exception);
        }

        if (! $response->successful()) {
            $body = Str::limit(trim($response->body()), 300);

            throw new RuntimeException("NVIDIA API request failed with status {$response->status()}: {$body}");
        }

        $content = Arr::get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            $finishReason = Arr::get($response->json(), 'choices.0.finish_reason', 'unknown');
            $completionTokens = Arr::get($response->json(), 'usage.completion_tokens', 'unknown');
            $hadReasoning = filled(Arr::get($response->json(), 'choices.0.message.reasoning_content')) || filled(Arr::get($response->json(), 'choices.0.message.reasoning'));

            throw new RuntimeException(sprintf(
                'NVIDIA API returned an empty article (finish_reason: %s, completion_tokens: %s, reasoning returned: %s).',
                is_scalar($finishReason) ? $finishReason : 'unknown',
                is_scalar($completionTokens) ? $completionTokens : 'unknown',
                $hadReasoning ? 'yes' : 'no',
            ));
        }

        try {
            $article = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('NVIDIA API returned invalid JSON: '.Str::limit($exception->getMessage(), 100), previous: $exception);
        }

        if (! is_array($article)) {
            throw new RuntimeException('NVIDIA API returned an invalid article.');
        }

        return $this->validateArticle($article);
    }

    /** @param array<int, string> $excludedTitles */
    private function requestArticle(string $apiKey, array $excludedTitles): Response
    {
        $excludedTitlesJson = json_encode(array_values($excludedTitles), JSON_THROW_ON_ERROR);
        $prompt = <<<PROMPT
Write one original, practical automotive maintenance article for Motologic readers. Choose a specific useful topic and do not reuse or closely paraphrase any title in this exclusion list: {$excludedTitlesJson}.
Use accurate, generally applicable guidance. Clearly tell readers to consult their owner's manual for vehicle-specific specifications. Do not invent statistics, citations, personal experiences, or product endorsements.
Return only one JSON object with exactly these keys: title, excerpt, body_markdown, seo_title, meta_description, tags.
title: a specific headline of at most 120 characters.
excerpt: a plain-text summary of 1-2 sentences and at most 500 characters.
body_markdown: 700-1000 words in Markdown with an engaging introduction, useful ## and ### sections, actionable steps or checklists, safety guidance where relevant, and a concise conclusion. Do not include a duplicate top-level title, HTML, links, or image placeholders.
seo_title: a search-friendly title of at most 60 characters.
meta_description: a compelling description of 120-155 characters.
tags: an array of 2-4 short, relevant topic names.
PROMPT;

        return Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout((int) config('blog.ai.timeout'))
            ->post(rtrim((string) config('blog.ai.base_url'), '/').'/chat/completions', [
                'model' => config('blog.ai.model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 1,
                'top_p' => 0.95,
                'max_tokens' => 16384,
                'seed' => 42,
                'stream' => false,
                'response_format' => ['type' => 'json_object'],
                'chat_template_kwargs' => ['enable_thinking' => true],
            ]);
    }

    /**
     * @param  array<mixed>  $article
     * @return array{title: string, excerpt: string, body_markdown: string, seo_title: string, meta_description: string, tags: array<int, string>}
     */
    private function validateArticle(array $article): array
    {
        $limits = [
            'title' => 120,
            'excerpt' => 500,
            'body_markdown' => 30000,
            'seo_title' => 60,
            'meta_description' => 320,
        ];

        foreach ($limits as $field => $limit) {
            if (! isset($article[$field]) || ! is_string($article[$field]) || trim($article[$field]) === '') {
                throw new RuntimeException("NVIDIA article is missing a valid {$field}.");
            }

            $article[$field] = trim($article[$field]);

            if (mb_strlen($article[$field]) > $limit) {
                throw new RuntimeException("NVIDIA article {$field} exceeds {$limit} characters.");
            }
        }

        if (mb_strlen($article['body_markdown']) < 200) {
            throw new RuntimeException('NVIDIA article body is too short.');
        }

        if (! isset($article['tags']) || ! is_array($article['tags']) || count($article['tags']) < 2 || count($article['tags']) > 4) {
            throw new RuntimeException('NVIDIA article must contain between 2 and 4 tags.');
        }

        $tags = [];

        foreach ($article['tags'] as $tag) {
            if (! is_string($tag) || trim($tag) === '' || mb_strlen(trim($tag)) > 80) {
                throw new RuntimeException('NVIDIA article contains an invalid tag.');
            }

            $tags[] = trim($tag);
        }

        return [
            'title' => $article['title'],
            'excerpt' => $article['excerpt'],
            'body_markdown' => $article['body_markdown'],
            'seo_title' => $article['seo_title'],
            'meta_description' => $article['meta_description'],
            'tags' => array_values(array_unique($tags)),
        ];
    }
}
