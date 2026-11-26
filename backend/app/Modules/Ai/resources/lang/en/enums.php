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
    'store_translation_section' => [
        'categories' => 'Categories',
        'products' => 'Own products',
        'variants' => 'Own product variants',
        'product_overrides' => 'Catalogue products',
        'variant_overrides' => 'Catalogue product variants',
        'flash_sales' => 'Flash sales',
        'banners' => 'Banners',
        'menu_links' => 'Menu links',
        'pages' => 'Pages',
        'email_templates' => 'Email templates',
        'store_settings' => 'Store identity & policies',
        'static_texts' => 'Storefront texts',
    ],
    'translation_item_status' => [
        'done' => 'Translated',
        'failed' => 'Failed',
    ],
    'ai_feature' => [
        'store_translation' => 'Paid store translation',
        'delta_translation' => 'Weekly delta translation',
        'product_translation' => 'Product “Translate with AI”',
        'catalog_translation' => 'Catalogue translation (admin)',
        'static_key_translation' => 'Static texts translation (admin)',
        'store_text_suggestion' => 'Store text suggestions',
    ],
];
