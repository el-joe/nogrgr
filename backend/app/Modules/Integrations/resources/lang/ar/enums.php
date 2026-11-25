<?php

return [
    'integration_section' => [
        'mail' => 'البريد',
        'google' => 'الدخول عبر Google',
        'apple' => 'الدخول عبر Apple',
        'broadcasting' => 'Reverb / Pusher',
        'cloudflare' => 'Cloudflare / DNS',
        'ai' => 'ترجمة الذكاء الاصطناعي',
    ],
    'setting_field_type' => [
        'text' => 'نص',
        'secret' => 'سرّي',
        'select' => 'اختيار',
        'toggle' => 'مفتاح',
        'email' => 'بريد إلكتروني',
        'url' => 'رابط',
        'number' => 'رقم',
        'file' => 'ملف',
    ],
    'mail_driver' => [
        'smtp' => 'SMTP',
        'log' => 'سجل (بدون إرسال)',
    ],
    'mail_encryption' => [
        'tls' => 'STARTTLS (587)',
        'ssl' => 'SSL/TLS (465)',
        'none' => 'بدون',
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
        'vendor_payments' => 'مدفوعات البائعين',
        'tenant_registration' => 'تسجيل المتاجر',
    ],
    'gateway_mode' => [
        'test' => 'اختبار',
        'live' => 'مباشر',
    ],
    'connection_status' => [
        'connected' => 'متصل',
        'failed' => 'فشل',
        'not_tested' => 'لم يُختبر',
        'not_configured' => 'غير مُعدّ',
    ],
    'connection_error_type' => [
        'misconfigured' => 'إعدادات مطلوبة ناقصة أو غير صالحة',
        'auth_failed' => 'رفض المزوّد بيانات الاعتماد',
        'rate_limited' => 'المزوّد يحدّ من الطلبات، حاول بعد قليل',
        'quota_exhausted' => 'نفدت حصة الحساب',
        'unreachable' => 'تعذّر الوصول إلى المزوّد',
        'unexpected_response' => 'أعاد المزوّد ردًا غير متوقّع',
    ],
    'payment_gateway_code' => [
        'cod' => 'الدفع عند الاستلام',
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
