<?php

return [
    'translation_provider' => [
        'openai' => 'OpenAI',
        'freellmapi' => 'FreeLLMAPI (مستضاف ذاتيًا)',
    ],
    'translation_purpose' => [
        'custom' => 'الترجمات المخصصة',
        'paid' => 'ترجمة المتجر الكاملة المدفوعة',
    ],
    'ai_error_type' => [
        'not_configured' => 'مزوّد الذكاء الاصطناعي غير مُعدّ بعد. أضف الرابط ومفتاح API من التكاملات › ترجمة الذكاء الاصطناعي.',
        'quota_exhausted' => 'نفدت حصة مزوّد الذكاء الاصطناعي. اشحن الحساب أو بدّل المزوّد.',
        'auth_failed' => 'رفض مزوّد الذكاء الاصطناعي مفتاح API. تحقّق من المفتاح وحاول مجددًا.',
        'rate_limited' => 'مزوّد الذكاء الاصطناعي يحدّ من عدد الطلبات. حاول بعد دقيقة.',
        'unavailable' => 'تعذّر الوصول إلى مزوّد الذكاء الاصطناعي حاليًا. تحقّق من الرابط أو حاول لاحقًا.',
        'invalid_response' => 'أعاد مزوّد الذكاء الاصطناعي ردًا غير متوقّع. حاول مجددًا أو اختر نموذجًا آخر.',
    ],
    'store_translation_section' => [
        'categories' => 'التصنيفات',
        'products' => 'المنتجات الخاصة',
        'variants' => 'متغيرات المنتجات الخاصة',
        'product_overrides' => 'منتجات الكتالوج',
        'variant_overrides' => 'متغيرات منتجات الكتالوج',
        'flash_sales' => 'العروض السريعة',
        'banners' => 'البانرات',
        'menu_links' => 'روابط القوائم',
        'pages' => 'الصفحات',
        'email_templates' => 'قوالب البريد الإلكتروني',
        'store_settings' => 'هوية المتجر والسياسات',
        'static_texts' => 'نصوص واجهة المتجر',
    ],
    'translation_item_status' => [
        'done' => 'مترجم',
        'failed' => 'فشل',
    ],
    'ai_feature' => [
        'store_translation' => 'ترجمة المتجر المدفوعة',
        'delta_translation' => 'الترجمة الأسبوعية للتغييرات',
        'product_translation' => 'ترجمة المنتج بالذكاء الاصطناعي',
        'catalog_translation' => 'ترجمة الكتالوج (الإدارة)',
        'static_key_translation' => 'ترجمة النصوص الثابتة (الإدارة)',
        'store_text_suggestion' => 'اقتراحات نصوص المتجر',
    ],
];
