<?php

return [
    'integration_section' => [
        'mail' => 'Mail',
        'google' => 'Google login',
        'apple' => 'Apple login',
        'broadcasting' => 'Reverb / Pusher',
        'cloudflare' => 'Cloudflare / DNS',
        'ai' => 'AI translation',
        'neozena' => 'Neozena catalog',
        'geocoding' => 'Geocoding',
        'billing' => 'Billing',
        'commerce' => 'Checkout & API',
        'domains' => 'Domains & DNS',
    ],
    'setting_field_type' => [
        'text' => 'Text',
        'secret' => 'Secret',
        'select' => 'Choice',
        'toggle' => 'Switch',
        'email' => 'E-mail',
        'url' => 'URL',
        'number' => 'Number',
        'file' => 'File',
        'textarea' => 'Long text',
        'ipv4' => 'IPv4 address',
    ],
    'mail_driver' => [
        'smtp' => 'SMTP',
        'log' => 'Log (no delivery)',
    ],
    'mail_encryption' => [
        'tls' => 'STARTTLS (587)',
        'ssl' => 'SSL/TLS (465)',
        'none' => 'None',
    ],
    'broadcast_driver' => [
        'reverb' => 'Laravel Reverb',
        'pusher' => 'Pusher Channels',
    ],
    'url_scheme' => [
        'https' => 'https',
        'http' => 'http',
    ],
    'gateway_purpose' => [
        'vendor_payments' => 'Vendor payments',
        'tenant_registration' => 'Tenant registration',
    ],
    'gateway_mode' => [
        'test' => 'Test',
        'live' => 'Live',
    ],
    'connection_status' => [
        'connected' => 'Connected',
        'failed' => 'Failed',
        'not_tested' => 'Not tested',
        'not_configured' => 'Not set',
    ],
    'connection_error_type' => [
        'misconfigured' => 'required settings are missing or invalid',
        'auth_failed' => 'the provider rejected the credentials',
        'rate_limited' => 'the provider is rate limiting requests, try again shortly',
        'quota_exhausted' => 'the account quota is exhausted',
        'unreachable' => 'the provider could not be reached',
        'unexpected_response' => 'the provider returned an unexpected answer',
    ],
    'payment_gateway_code' => [
        'cod' => 'Cash on delivery',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'paymob' => 'Paymob',
        'tap' => 'Tap',
        'myfatoorah' => 'MyFatoorah',
        'moyasar' => 'Moyasar',
        'tabby' => 'Tabby',
        'tamara' => 'Tamara',
    ],
];
