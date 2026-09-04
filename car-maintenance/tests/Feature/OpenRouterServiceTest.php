<?php

namespace Tests\Feature;

use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    public function test_returns_vehicle_specific_service_interval(): void
    {
        config([
            'openrouter.api_key' => 'test-key',
            'openrouter.base_url' => 'https://openrouter.test/api/v1',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://openrouter.test/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'viscosity' => '0W-20',
                            'oil_type' => 'Full synthetic',
                            'specification' => 'API SP',
                            'capacity_liters' => 4.2,
                            'interval_months' => 12,
                            'interval_kilometers' => 10000,
                            'interval_basis' => 'Manufacturer normal-service schedule.',
                            'brands' => [],
                            'notes' => 'Use the severe schedule for frequent idling.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $result = app(OpenRouterService::class)->getOilSuggestions('Toyota', 'Corolla', 2020, 'PH', 45000);

        $this->assertSame(12, $result['interval_months']);
        $this->assertSame(10000, $result['interval_kilometers']);
        Http::assertSent(fn ($request): bool => str_contains(
            $request['messages'][0]['content'],
            'manufacturer-backed engine oil and oil-change service interval'
        ));
    }

    public function test_rejects_an_invalid_service_interval(): void
    {
        config(['openrouter.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'viscosity' => '0W-20',
                            'interval_months' => 0,
                            'interval_kilometers' => 10000,
                            'brands' => [],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $result = app(OpenRouterService::class)->getOilSuggestions('Toyota', 'Corolla', 2020, 'PH');

        $this->assertNull($result);
    }
}
