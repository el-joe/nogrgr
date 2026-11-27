<?php

return [
    'provisioning_failed' => [
        'title' => 'فشل إعداد متجر',
        'body' => 'تعذّر إعداد :store. افتح المتجر لعرض الخطأ وإعادة المحاولة.',
    ],
    'store_provisioned' => [
        'title' => 'متجر جديد أصبح متاحاً',
        'body' => 'اكتمل إعداد :store وأصبح نشطاً.',
    ],
    'compliance_submitted' => [
        'title' => 'طلب توثيق جديد',
        'body' => 'أرسل :store مستندات التوثيق للمراجعة.',
    ],
    'compliance_resubmitted' => [
        'title' => 'إعادة إرسال التوثيق',
        'body' => 'حدّث :store بيانات التوثيق وأعاد إرسالها للمراجعة.',
    ],
    'owner_compliance' => [
        'title' => 'مراجعة الامتثال: :status',
        'body' => 'أصبحت حالة مراجعة الامتثال لمتجر :store الآن: :status.',
    ],
    'owner_domain_dns_failed' => [
        'title' => 'النطاق :domain يحتاج إلى مراجعة',
        'body' => 'لم يتم العثور على سجلات DNS للنطاق :domain (:store) في آخر فحص. قد لا يتمكن الزوار من فتح المتجر عليه.',
    ],
    'admin_domain_dns_failed' => [
        'title' => 'فشل فحص DNS: :domain',
        'body' => 'سجلات DNS للنطاق :domain (:store) مفقودة بعد أن كانت موجودة.',
    ],
];
