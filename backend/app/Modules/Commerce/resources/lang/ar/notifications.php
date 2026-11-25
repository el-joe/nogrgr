<?php

return [
    'store_new_order' => ['title' => 'طلب جديد :number', 'body' => 'قام :customer بتقديم الطلب :number (:total).'],
    'store_order_cancelled' => ['title' => 'تم إلغاء الطلب :number', 'body' => 'تم إلغاء الطلب :number (:total).'],
    'admin_order_paid' => ['title' => 'تم دفع الطلب :number', 'body' => 'استلم متجر :store طلباً مدفوعاً :number (:total).'],
    'admin_order_cancelled' => ['title' => 'تم إلغاء الطلب :number', 'body' => 'تم إلغاء الطلب :number (:total) في متجر :store.'],
    'customer' => [
        'greeting' => 'مرحباً :customer_first_name،',
        'view_order' => 'عرض الطلب',
        'confirmation' => ['subject' => 'تم تأكيد الطلب :order_number', 'body' => 'شكراً لطلبك :order_number من :store_name. الإجمالي: :order_total.'],
        'shipped' => ['subject' => 'تم شحن الطلب :order_number', 'body' => 'طلبك :order_number في الطريق إليك. رقم التتبع: :shipment_tracking_no.'],
        'delivered' => ['subject' => 'تم توصيل الطلب :order_number', 'body' => 'تم توصيل طلبك :order_number. نتمنى أن ينال إعجابك!'],
        'cancelled' => ['subject' => 'تم إلغاء الطلب :order_number', 'body' => 'تم إلغاء طلبك :order_number من :store_name.'],
    ],
];
