<?php

return [
    'coupon_type' => ['percentage' => 'نسبة مئوية', 'fixed' => 'مبلغ ثابت', 'free_shipping' => 'شحن مجاني'],
    'coupon_status' => ['active' => 'مفعّلة', 'scheduled' => 'مجدولة', 'expired' => 'منتهية', 'used_up' => 'مستنفدة', 'inactive' => 'متوقفة'],
    'flash_sale_status' => ['live' => 'جارٍ', 'scheduled' => 'مجدول', 'ended' => 'منتهٍ', 'inactive' => 'متوقف'],
    'promotion_scope' => ['all' => 'كل المتاجر', 'selected' => 'متاجر محددة'],
    'discount_source' => ['central' => 'المنصة', 'vendor' => 'المتجر'],
    'promotion_kind' => ['coupon' => 'قسيمة', 'flash_sale' => 'تخفيض سريع'],
    'profit_type' => ['percentage' => 'نسبة مئوية', 'fixed' => 'ثابت'],
    'coupon_rejection' => [
        'not_found' => 'غير موجودة', 'inactive' => 'غير مفعّلة', 'not_started' => 'لم تبدأ', 'expired' => 'منتهية',
        'country_not_allowed' => 'الدولة غير مسموحة', 'minimum_spend' => 'أقل من الحد الأدنى', 'usage_limit_reached' => 'بلغت حد الاستخدام',
        'customer_limit_reached' => 'بلغ العميل الحد', 'first_order_only' => 'للطلب الأول فقط', 'sign_in_required' => 'يلزم تسجيل الدخول',
        'nothing_to_discount' => 'لا يوجد ما يُخصم',
    ],
    'order_status' => ['pending_payment' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'processing' => 'قيد التجهيز', 'shipped' => 'تم الشحن', 'delivered' => 'تم التوصيل', 'completed' => 'مكتمل', 'cancelled' => 'ملغي', 'refunded' => 'مسترد', 'returned' => 'مرتجع'],
    'payment_status' => ['unpaid' => 'غير مدفوع', 'pending' => 'قيد الانتظار', 'paid' => 'مدفوع', 'failed' => 'فشل', 'refunded' => 'مسترد', 'cod' => 'الدفع عند الاستلام'],
    'payment_attempt_status' => ['initiated' => 'بدأت', 'pending' => 'قيد الانتظار', 'paid' => 'مدفوع', 'failed' => 'فشل', 'cancelled' => 'ملغي'],
    'order_kind' => ['catalog' => 'طلب الكتالوج', 'own' => 'طلب منتجات المتجر'],
    'stock_reservation_status' => ['reserved' => 'محجوز', 'committed' => 'مؤكد', 'deducted' => 'مخصوم', 'released' => 'محرر'],
    'cart_line_issue' => ['inactive' => 'غير متاح', 'variant_unavailable' => 'الخيار غير متاح', 'not_sold_in_country' => 'غير متاح في دولتك', 'out_of_stock' => 'نفد من المخزون', 'insufficient_stock' => 'مخزون محدود'],
    'payment_source' => ['tenant' => 'حساب المتجر', 'central' => 'حساب المنصة'],
    'gateway_connection' => ['connected' => 'متصل', 'not_connected' => 'غير متصل'],
    'checkout_attempt_status' => ['processing' => 'قيد المعالجة', 'completed' => 'مكتمل', 'failed' => 'فشل'],
    'checkout_block' => ['empty_cart' => 'السلة فارغة', 'sign_in_required' => 'تسجيل الدخول مطلوب', 'no_address' => 'لا يوجد عنوان', 'unavailable_items' => 'منتجات غير متاحة', 'shipping_unavailable' => 'الشحن غير متاح', 'no_gateway' => 'لا توجد طريقة دفع', 'gateway_unavailable' => 'طريقة الدفع غير متاحة', 'coupon_invalid' => 'قسيمة غير صالحة', 'stock_changed' => 'تغير المخزون'],
    'actor_type' => ['customer' => 'العميل', 'vendor' => 'المتجر', 'admin' => 'المسؤول', 'system' => 'النظام', 'gateway' => 'بوابة الدفع'],
];
