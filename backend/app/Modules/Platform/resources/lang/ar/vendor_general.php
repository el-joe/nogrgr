<?php

return [
    'title' => 'عام',
    'store' => 'المتجر',
    'save' => 'حفظ',
    'saved' => 'تم حفظ إعدادات المتجر.',
    'requested' => 'تم إرسال طلب التغيير إلى فريق نقرقر.',
    'request_change' => 'طلب تغيير',
    'pending_locked' => 'يوجد طلب بانتظار المراجعة بالفعل.',
    'list_separator' => '، ',
    'fields' => [
        'store_name' => 'اسم المتجر',
        'support_email' => 'بريد الدعم',
        'support_email_help' => 'يظهر للمتسوقين الذين يحتاجون مساعدة في طلباتهم.',
        'profit_percent' => 'نسبة الربح الافتراضية %',
        'profit_percent_help' => 'تُطبق على المنتجات المركزية التي ليس لها قاعدة ربح خاصة.',
        'timezone' => 'المنطقة الزمنية',
    ],
    'countries' => [
        'title' => 'الدول',
        'empty' => 'متجرك لا يبيع في أي دولة بعد. اطلب الدول التي تريد البيع فيها.',
    ],
    'categories' => [
        'title' => 'الفئات',
        'empty' => 'لم يتم اختيار فئات بعد. اطلب الفئات التي يبيعها متجرك.',
    ],
    'pending' => [
        'line' => 'طلب قيد المراجعة: :changes (أُرسل :ago).',
        'add' => 'إضافة :items',
        'remove' => 'إزالة :items',
    ],
    'modal' => [
        'countries' => [
            'title' => 'طلب تغيير الدول',
            'add' => 'إضافة دول',
            'add_placeholder' => 'اختر الدول',
            'remove' => 'إزالة دول',
            'remove_placeholder' => 'اختر الدول',
        ],
        'categories' => [
            'title' => 'طلب تغيير الفئات',
            'add' => 'إضافة فئات',
            'add_placeholder' => 'اختر الفئات',
            'remove' => 'إزالة فئات',
            'remove_placeholder' => 'اختر الفئات',
        ],
        'reason' => 'السبب',
        'cancel' => 'إلغاء',
        'send' => 'إرسال الطلب',
    ],
    'errors' => [
        'nothing_selected' => 'اختر ما تريد إضافته أو إزالته.',
        'last_country' => 'يجب أن يحتفظ متجرك بدولة بيع واحدة على الأقل.',
    ],
];
