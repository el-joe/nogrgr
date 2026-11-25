<?php

return [
    'errors' => [
        'order_not_found' => 'الطلب غير موجود.',
        'not_cancellable' => 'لم يعد بالإمكان إلغاء هذا الطلب.',
        'not_payable' => 'لا يمكن دفع هذا الطلب.',
        'payment_not_found' => 'عملية الدفع غير موجودة.',
        'checkout_in_progress' => 'جارٍ تقديم طلبك بالفعل. يرجى الانتظار قليلاً.',
        'idempotency_mismatch' => 'تم استخدام مفتاح الطلب هذا لطلب مختلف.',
        'transition' => 'لا يمكن نقل الطلب من ":from" إلى ":to".',
        'gateway_unknown' => 'بوابة دفع غير معروفة.',
        'webhook_rejected' => 'تعذر التحقق من الإشعار.',
    ],
    'messages' => [
        'cancelled' => 'تم إلغاء طلبك.',
    ],
    'notes' => [
        'payment_timeout' => 'أُلغي: لم يكتمل الدفع في الوقت المحدد.',
    ],
    'payment' => [
        'initiated' => 'بدأت عملية الدفع.',
        'pending' => 'جارٍ معالجة الدفع.',
        'paid' => 'تم الدفع بنجاح.',
        'failed' => 'فشل الدفع. يرجى المحاولة مرة أخرى.',
        'cancelled' => 'تم إلغاء الدفع.',
    ],
    'payment_description' => 'الطلب :numbers',
];
