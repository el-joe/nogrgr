<?php

/*
|--------------------------------------------------------------------------
| AI providers (decisions #43 / #44 / #69)
|--------------------------------------------------------------------------
|
| Translation may run on OpenAI or a self-hosted OpenAI-compatible FreeLLMAPI
| gateway. `custom_provider` serves per-field / single-key translations and
| suggestions; `paid_provider` serves purchased full-store translations. Every
| other AI tool uses OpenAI only. Connection values come only from Admin ›
| Integrations › AI translation (IntegrationConfigOverlay): without an API key a
| provider reports "not configured".
|
*/

return [
    'timeout' => 60,

    'translation' => [
        'provider' => 'freellmapi',
        'custom_provider' => 'freellmapi',
        'paid_provider' => 'openai',
    ],

    'providers' => [
        'openai' => [
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => null,
            'model' => 'gpt-4.1-mini',
        ],
        'freellmapi' => [
            'base_url' => 'http://localhost:3001/v1',
            'api_key' => null,
            'model' => 'auto',
        ],
    ],
];
