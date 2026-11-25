<?php

/*
| Ledger transaction texts (decision #72). `descriptions` are stored in English with the
| transaction (search / export); `titles` / `subtitles` are rendered from its meta.
*/
return [
    'descriptions' => [
        'order_vendor_share' => 'حصة التاجر من الطلب #:number',
        'order_platform_share' => 'حصة المنصة من الطلب #:number',
        'order_void' => 'إلغاء / استرداد الطلب #:number',
        'refund' => 'استرداد :reference للطلب #:number',
        'settlement' => 'تسوية :reference للطلب #:number',
        'settlement_offset' => 'تسوية :reference للطلب #:number (مقاصة)',
        'payout' => 'الدفعة :reference',
        'payout_cancelled' => 'إلغاء الدفعة :reference',
        'subscription' => 'فاتورة اشتراك :number',
        'addon' => 'فاتورة إضافة :number',
    ],
    'titles' => [
        'order_vendor_share' => 'حصة التاجر من الطلب #:number',
        'order_platform_share' => 'حصة المنصة من الطلب #:number',
        'order_void' => 'إلغاء / استرداد الطلب #:number',
        'refund' => 'استرداد :reference · الطلب #:number',
        'settlement' => 'تسوية :reference · الطلب #:number',
        'settlement_offset' => 'تسوية :reference · الطلب #:number',
        'payout' => 'الدفعة :reference',
        'payout_cancelled' => 'إلغاء الدفعة :reference',
        'subscription' => 'فاتورة اشتراك :number',
        'addon' => 'فاتورة إضافة :number',
    ],
    'subtitles' => [
        'order_vendor_share' => ':percent% من :total',
        'order_platform_share' => 'حصّلها المتجر · :total',
        'order_void' => 'عكس حصة الطلب',
        'refund' => 'يتحمله المتجر',
        'settlement' => ':method',
        'settlement_offset' => 'مخصومة من المدفوعات',
        'payout' => ':method',
        'payout_cancelled' => 'عكس',
        'subscription' => 'مدفوعة للمنصة',
        'addon' => 'مدفوعة للمنصة',
    ],
];
