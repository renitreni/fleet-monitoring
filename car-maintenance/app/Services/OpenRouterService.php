<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenRouterService
{
    /** Get vehicle-specific oil and service-interval suggestions. */
    public function getOilSuggestions(string $make, string $model, int $year, string $country, ?int $mileage = null): ?array
    {
        $mileageNote = $mileage !== null
            ? PHP_EOL.'The vehicle currently has '.number_format($mileage).' kilometers on the odometer.'
            : '';

        $prompt = <<<PROMPT
Recommend the manufacturer-backed engine oil and oil-change service interval for a {$year} {$make} {$model} vehicle sold in {$country}. The vehicle may be a car or a two-wheeled vehicle, so infer its category from the make and model and only recommend products formulated for that category.{$mileageNote}
Prefer the official maintenance schedule for this market. If an exact schedule is uncertain, choose a conservative interval and explain that uncertainty. Never invent a source.
Return one JSON object with exactly these keys: viscosity, oil_type, specification, capacity_liters, interval_months, interval_kilometers, interval_basis, products, notes.
interval_months and interval_kilometers must be positive integers. interval_basis must briefly identify whether the recommendation is manufacturer-backed or conservative.
products must contain exactly three objects with exactly these string keys: brand, product, role, reason. Use an exact, purchasable product name rather than only a brand name. The first product must be the oil assigned or officially recommended for this vehicle and its role must be "Assigned product". The second and third products must be compatible alternatives and their role must be "Alternative". Every product must match the recommended viscosity and specification. If the exact factory-filled product is not documented, use the vehicle manufacturer's genuine/OEM oil for this market as the assigned product and explain the basis in reason.
PROMPT;

        $suggestions = $this->requestCompletion($prompt);

        if ($suggestions === null) {
            return null;
        }

        // Validate the structure of the response
        if (
            empty($suggestions['viscosity'])
            || ! isset($suggestions['products'])
            || ! is_array($suggestions['products'])
            || count($suggestions['products']) !== 3
            || ! $this->hasValidProducts($suggestions['products'])
            || ! is_int($suggestions['interval_months'] ?? null)
            || ! is_int($suggestions['interval_kilometers'] ?? null)
            || $suggestions['interval_months'] < 1
            || $suggestions['interval_months'] > 24
            || $suggestions['interval_kilometers'] < 100
            || $suggestions['interval_kilometers'] > 50000
        ) {
            Log::error('OpenRouter API response missing required fields', [
                'suggestions' => $suggestions,
            ]);

            return null;
        }

        return $suggestions;
    }

    /**
     * @param  array<int, mixed>  $products
     */
    protected function hasValidProducts(array $products): bool
    {
        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                return false;
            }

            foreach (['brand', 'product', 'role', 'reason'] as $key) {
                if (! isset($product[$key]) || ! is_string($product[$key]) || trim($product[$key]) === '') {
                    return false;
                }
            }

            $expectedRole = $index === 0 ? 'Assigned product' : 'Alternative';

            if ($product['role'] !== $expectedRole) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send a chat completion request to the OpenRouter API and decode the JSON answer.
     *
     * Returns null on any failure (missing key, API error, invalid JSON) so callers
     * can degrade gracefully without surfacing errors to the user.
     */
    protected function requestCompletion(string $prompt): ?array
    {
        // Check if API key is configured
        $apiKey = config('openrouter.api_key');
        if (empty($apiKey)) {
            Log::warning('OpenRouter API key is not configured');

            return null;
        }

        // Prepare the request data
        $requestData = [
            'model' => config('openrouter.model'),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            // Make the API request
            $response = Http::withToken($apiKey)
                ->timeout(config('openrouter.timeout'))
                ->post(config('openrouter.base_url').'/chat/completions', $requestData);

            // Check if the request was successful
            if (! $response->successful()) {
                Log::error('OpenRouter API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            // Parse the response
            $responseData = $response->json();

            // Extract the content from the response
            $content = Arr::get($responseData, 'choices.0.message.content');

            if (empty($content)) {
                Log::error('OpenRouter API returned empty content', [
                    'response' => $responseData,
                ]);

                return null;
            }

            // Parse the JSON content
            $suggestions = json_decode($content, true);

            // Check if the JSON was valid
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($suggestions)) {
                Log::error('Failed to parse OpenRouter API response as JSON', [
                    'content' => $content,
                    'error' => json_last_error_msg(),
                ]);

                return null;
            }

            return $suggestions;
        } catch (Throwable $e) {
            Log::error('Exception occurred while calling OpenRouter API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
