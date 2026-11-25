<?php

return [
    'translation_provider' => [
        'openai' => 'OpenAI',
        'freellmapi' => 'FreeLLMAPI (self-hosted)',
    ],
    'translation_purpose' => [
        'custom' => 'Custom translations',
        'paid' => 'Paid full-store translations',
    ],
    'ai_error_type' => [
        'not_configured' => 'The AI provider is not configured yet. Add its URL and API key in Integrations › AI translation.',
        'quota_exhausted' => 'The AI provider quota is exhausted. Top up the account or switch provider.',
        'auth_failed' => 'The AI provider rejected the API key. Check the key and try again.',
        'rate_limited' => 'The AI provider is rate limiting requests. Try again in a minute.',
        'unavailable' => 'The AI provider is unreachable right now. Check the base URL or try again later.',
        'invalid_response' => 'The AI provider returned an unexpected answer. Try again or choose another model.',
    ],
];
