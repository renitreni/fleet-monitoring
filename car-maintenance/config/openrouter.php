<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for the OpenRouter API
    | integration. The API key and default model can be set via environment
    | variables.
    |
    */

    'api_key' => env('OPENROUTER_API_KEY'),

    'model' => env('OPENROUTER_MODEL', 'openai/gpt-3.5-turbo'),

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'timeout' => env('OPENROUTER_TIMEOUT', 30),
];
