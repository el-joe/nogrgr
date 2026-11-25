<?php

return [
    'tenant_status' => [
        'pending' => 'قيد الانتظار',
        'provisioning' => 'جارٍ الإعداد',
        'active' => 'نشط',
        'failed' => 'فشل الإعداد',
        'suspended' => 'موقوف',
        'trial' => 'تجريبي',
        'cancelled' => 'ملغى',
    ],
    'domain_type' => [
        'subdomain' => 'نطاق فرعي',
        'custom' => 'نطاق مخصص',
    ],
    'plan_assignment' => [
        'trial' => 'البدء بالفترة التجريبية للباقة',
        'manual_paid' => 'يدوي (تعليم كمدفوع)',
        'invoiced' => 'نشط الآن مع فاتورة للدفع',
    ],
    'registration_status' => [
        'payment_pending' => 'بانتظار الدفع',
        'payment_failed' => 'فشل الدفع',
        'email_unverified' => 'البريد غير مؤكد',
        'incomplete' => 'غير مكتمل',
        'completed' => 'مكتمل',
    ],
    'registration_step' => [
        'payment' => 'الدفع',
        'email_verification' => 'تأكيد البريد',
        'store_details' => 'بيانات المتجر',
        'done' => 'تم',
    ],
    'compliance_status' => [
        'needs_review' => 'بحاجة إلى مراجعة',
        'needs_action' => 'بحاجة إلى إجراء',
        'verified' => 'موثّق',
        'rejected' => 'مرفوض',
    ],
    'compliance_risk' => [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'مرتفع',
    ],
    'compliance_document_type' => [
        'commercial_registration' => 'السجل التجاري',
        'national_id' => 'الهوية الوطنية',
        'tax_certificate' => 'الشهادة الضريبية',
        'trade_licence' => 'الرخصة التجارية',
        'bank_letter' => 'خطاب البنك (الآيبان)',
        'other' => 'مستند آخر',
    ],
    'compliance_document_type_short' => [
        'commercial_registration' => 'السجل',
        'national_id' => 'الهوية',
        'tax_certificate' => 'الضريبة',
        'trade_licence' => 'الرخصة',
        'bank_letter' => 'الآيبان',
        'other' => 'أخرى',
    ],
    'compliance_document_status' => [
        'review' => 'قيد المراجعة',
        'verified' => 'موثّق',
        'needs_action' => 'بحاجة إلى إجراء',
    ],
    'domain_request_status' => [
        'pending' => 'قيد الانتظار',
        'connected' => 'متصل',
        'removed' => 'محذوف',
    ],
    'dns_check_status' => [
        'unchecked' => 'لم يُفحص',
        'ok' => 'كل السجلات سليمة',
        'missing' => 'سجلات ناقصة',
        'error' => 'تعذّر الفحص',
    ],
    'ssl_status' => [
        'unknown' => 'غير معروف',
        'valid' => 'صالحة',
        'invalid' => 'غير صالحة',
    ],
    'impersonation_reason' => [
        'support_ticket' => 'تذكرة دعم',
        'onboarding' => 'مساعدة في البدء',
        'bug_investigation' => 'تحقيق في خلل',
        'compliance_review' => 'مراجعة الامتثال',
        'other' => 'أخرى',
    ],
    'owner_store_state' => [
        'live' => 'مباشر',
        'trial' => 'تجربة',
        'past_due' => 'متأخر السداد',
        'suspended' => 'معلّق',
        'not_launched' => 'لم يُطلق بعد',
    ],
];
