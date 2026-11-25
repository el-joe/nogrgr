<?php

/*
|--------------------------------------------------------------------------
| AI providers (decisions #43 / #44)
|--------------------------------------------------------------------------
|
| Translation may run on OpenAI or a self-hosted OpenAI-compatible FreeLLMAPI
| gateway. `custom_provider` serves per-field / single-key translations and
| suggestions; `paid_provider` serves purchased full-store translations. Every
| other AI tool uses OpenAI only. Admin › Integrations › AI translation overrides
| these values at runtime (Integrations\Services\IntegrationConfigOverlay).
|
*/

return [
    'timeout' => (int) env('AI_TIMEOUT', 60),

    'translation' => [
        'provider' => env('AI_TRANSLATION_PROVIDER', 'freellmapi'),
        'custom_provider' => env('AI_TRANSLATION_CUSTOM_PROVIDER', 'freellmapi'),
        'paid_provider' => env('AI_TRANSLATION_PAID_PROVIDER', 'openai'),
    ],

    'providers' => [
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_OPENAI_TRANSLATION_MODEL', 'gpt-4.1-mini'),
        ],
        'freellmapi' => [
            'base_url' => env('FREELLMAPI_BASE_URL', 'http://localhost:3001/v1'),
            'api_key' => env('FREELLMAPI_API_KEY'),
            'model' => env('FREELLMAPI_MODEL', 'auto'),
        ],
    ],
];
