<?php

return [
    'tabs' => [
        'platform' => 'دعم المنصة',
        'customers' => 'العملاء',
    ],
    'title' => 'تذاكر العملاء',
    'subtitle' => 'الأسئلة التي أرسلها عملاؤك من التطبيق أو من صفحات حساباتهم في متجرك.',
    'list' => 'تذاكر العملاء',
    'search' => 'ابحث بالمرجع أو الموضوع أو العميل',
    'status_filter' => 'الحالة',
    'active' => 'النشطة',
    'customer' => 'العميل',
    'order' => 'الطلب :number',
    'topic' => 'الموضوع',
    'new_badge' => 'جديد',
    'opened_at' => 'فُتحت :date',
    'empty' => [
        'title' => 'لا توجد تذاكر عملاء بعد',
        'description' => 'عندما يتواصل معك عميل من حسابه أو من التطبيق تظهر التذكرة هنا.',
        'filtered' => 'لا توجد تذاكر عملاء تطابق هذه الفلاتر.',
    ],
    'actions' => [
        'resolve' => 'تعيين كمحلولة',
        'resolve_title' => 'حل التذكرة :reference؟',
        'resolve_message' => 'سيتم إبلاغ العميل بحل المشكلة، وأي رسالة جديدة منه تعيد فتحها.',
        'close' => 'إغلاق',
        'close_title' => 'إغلاق التذكرة :reference؟',
        'close_message' => 'لن يتمكن العميل من الرد بعد الآن، ويمكنك إعادة فتحها لاحقًا.',
        'reopen' => 'إعادة الفتح',
        'reopen_title' => 'إعادة فتح التذكرة :reference؟',
        'reopen_message' => 'تصبح التذكرة نشطة مجددًا ويمكن للعميل الرد.',
    ],
    'sent' => 'تم إرسال الرد إلى العميل.',
    'status_saved' => 'حالة التذكرة :reference الآن: :status.',
    'notifications' => [
        'ticket_replied' => [
            'title' => 'رد جديد على تذكرتك :reference',
            'body' => 'ردّ :store على «:subject».',
        ],
        'ticket_resolved' => [
            'title' => 'تم حل التذكرة :reference',
            'body' => 'عيّن :store «:subject» كمحلولة. رُد إذا كنت لا تزال بحاجة إلى مساعدة.',
        ],
        'ticket_closed' => [
            'title' => 'تم إغلاق التذكرة :reference',
            'body' => 'أغلق :store «:subject».',
        ],
        'ticket_reopened' => [
            'title' => 'أُعيد فتح التذكرة :reference',
            'body' => 'أعاد :store فتح «:subject». يمكنك الرد مجددًا.',
        ],
    ],
    'mail' => [
        'greeting' => 'مرحبًا :name،',
        'cta' => 'عرض التذكرة',
    ],
    'api' => [
        'opened' => 'تم إرسال التذكرة :reference. سيرد المتجر هنا.',
        'replied' => 'تم إرسال الرسالة.',
        'closed' => 'تم إغلاق التذكرة :reference.',
    ],
    'errors' => [
        'order' => 'اختر أحد طلباتك.',
    ],
];
