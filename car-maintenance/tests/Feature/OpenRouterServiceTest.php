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
                            'products' => [
                                [
                                    'brand' => 'Toyota',
                                    'product' => 'Toyota Genuine Motor Oil 0W-20',
                                    'role' => 'Assigned product',
                                    'reason' => 'The OEM oil for this vehicle.',
                                ],
                                [
                                    'brand' => 'Mobil 1',
                                    'product' => 'Mobil 1 Advanced Fuel Economy 0W-20',
                                    'role' => 'Alternative',
                                    'reason' => 'Meets the required specification.',
                                ],
                                [
                                    'brand' => 'Castrol',
                                    'product' => 'Castrol EDGE 0W-20 Advanced Full Synthetic',
                                    'role' => 'Alternative',
                                    'reason' => 'Meets the required specification.',
                                ],
                            ],
                            'notes' => 'Use the severe schedule for frequent idling.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $result = app(OpenRouterService::class)->getOilSuggestions('Toyota', 'Corolla', 2020, 'PH', 45000);

        $this->assertSame(12, $result['interval_months']);
        $this->assertSame(10000, $result['interval_kilometers']);
        $this->assertCount(3, $result['products']);
        $this->assertSame('Assigned product', $result['products'][0]['role']);
        Http::assertSent(fn ($request): bool => str_contains(
            $request['messages'][0]['content'],
            'manufacturer-backed engine oil and oil-change service interval'
        ) && str_contains(
            $request['messages'][0]['content'],
            'products must contain exactly three objects'
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
                            'products' => [],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $result = app(OpenRouterService::class)->getOilSuggestions('Toyota', 'Corolla', 2020, 'PH');

        $this->assertNull($result);
    }

    public function test_rejects_products_when_the_assigned_product_is_not_first(): void
    {
        config(['openrouter.api_key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'viscosity' => '0W-20',
                            'interval_months' => 12,
                            'interval_kilometers' => 10000,
                            'products' => [
                                ['brand' => 'Mobil 1', 'product' => 'Mobil 1 0W-20', 'role' => 'Alternative', 'reason' => 'Compatible.'],
                                ['brand' => 'Toyota', 'product' => 'Toyota Genuine Motor Oil 0W-20', 'role' => 'Assigned product', 'reason' => 'OEM oil.'],
                                ['brand' => 'Castrol', 'product' => 'Castrol EDGE 0W-20', 'role' => 'Alternative', 'reason' => 'Compatible.'],
                            ],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $result = app(OpenRouterService::class)->getOilSuggestions('Toyota', 'Corolla', 2020, 'PH');

        $this->assertNull($result);
    }
}
