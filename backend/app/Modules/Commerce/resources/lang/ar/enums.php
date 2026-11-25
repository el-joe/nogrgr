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
];
