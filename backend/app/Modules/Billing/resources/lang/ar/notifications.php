<?php

return [
    'greeting' => 'مرحبًا :name،',
    'open_billing' => 'فتح الفوترة',
    'invoice_issued' => [
        'title' => 'إصدار الفاتورة :number',
        'body' => 'صدرت فاتورة لـ :store بقيمة :total.',
        'vendor_subject' => 'فاتورة جديدة :invoice_number',
        'vendor_body' => 'صدرت الفاتورة :invoice_number بقيمة :invoice_total لمتجر :store_name وتستحق في :invoice_due_date.',
    ],
    'invoice_paid' => [
        'title' => 'دفع الفاتورة :number',
        'body' => 'دفع :store مبلغ :total.',
        'vendor_subject' => 'تم استلام دفعة الفاتورة :invoice_number',
        'vendor_body' => 'شكرًا لك. استلمنا :invoice_total للفاتورة :invoice_number.',
    ],
    'payment_failed' => [
        'title' => 'فشل الدفعة :reference',
        'body' => 'فشلت دفعة بقيمة :amount من :store عبر :gateway.',
        'vendor_subject' => 'لم تتم عملية الدفع',
        'vendor_body' => 'فشلت دفعتك بقيمة :payment_amount عبر :payment_gateway للفاتورة :invoice_number. حاول مجددًا أو استخدم وسيلة دفع أخرى.',
    ],
    'payment_refunded' => [
        'title' => 'استرداد الدفعة :reference',
        'body' => 'تم استرداد :amount إلى :store عبر :gateway.',
        'vendor_subject' => 'تم الاسترداد',
        'vendor_body' => 'استرددنا :payment_amount عبر :payment_gateway للفاتورة :invoice_number.',
    ],
    'subscription_activated' => [
        'title' => 'تفعيل الاشتراك',
        'body' => 'أصبح :store على باقة :plan حتى :date.',
        'vendor_subject' => 'باقة :plan_name مفعّلة',
        'vendor_body' => 'اشتراك :plan_name لمتجر :store_name مفعّل حتى :subscription_ends_at.',
    ],
    'subscription_past_due' => [
        'title' => 'اشتراك متأخر السداد',
        'body' => 'تعذّر تجديد باقة :plan لمتجر :store.',
        'vendor_subject' => 'فشل تجديد باقتك',
        'vendor_body' => 'تعذّر تجديد باقة :plan_name لمتجر :store_name. ادفع الفاتورة المفتوحة ليبقى متجرك متاحًا.',
    ],
    'subscription_cancelled' => [
        'title' => 'إلغاء الاشتراك',
        'body' => 'تم إلغاء باقة :plan لمتجر :store (تنتهي :date).',
    ],
    'subscription_renewed' => [
        'title' => 'تجديد الاشتراك',
        'body' => 'تم تجديد باقة :plan لمتجر :store حتى :date.',
    ],
    'invoice_voided' => [
        'title' => 'إلغاء الفاتورة :number',
        'body' => 'تم إلغاء فاتورة متجر :store بقيمة :total.',
    ],
    'subscription_expired' => [
        'title' => 'انتهاء الاشتراك',
        'body' => 'انتهت باقة :plan لمتجر :store.',
        'vendor_subject' => 'انتهى اشتراكك',
        'vendor_body' => 'انتهت باقة :plan_name لمتجر :store_name. جدّدها لإعادة تفعيل متجرك.',
    ],
];
