<?php

return [
    'support_request' => [
        'title' => 'طلب دعم جديد :ticket',
        'body' => 'كتب :name (:email) بخصوص ":subject".',
    ],
    'review_pending' => [
        'title' => 'تقييم بانتظار الموافقة',
        'body' => 'قيّم :author المنتج ":product" بـ :rating/5. وافق عليه لنشره في متجرك.',
    ],
    'review_published' => [
        'title' => 'تقييم منتج جديد',
        'body' => 'قيّم :author المنتج ":product" بـ :rating/5.',
    ],
    'customer' => [
        'review_published' => ['title' => 'تقييمك منشور الآن', 'body' => 'شكرًا لك! تقييمك لـ :product منشور الآن في :store.'],
        'review_rejected' => ['title' => 'لم يتم نشر تقييمك', 'body' => 'تقييمك لـ :product لا يتوافق مع إرشادات التقييم في :store.'],
    ],
];
