<?php

return [
    'return_status' => ['pending' => 'قيد المراجعة', 'awaiting_info' => 'بانتظار معلومات', 'approved' => 'تمت الموافقة', 'item_received' => 'تم الاستلام', 'inspected' => 'الفحص', 'refunded' => 'تم الاسترداد', 'exchange_shipped' => 'تم شحن الاستبدال', 'exchanged' => 'تم الاستبدال', 'rejected' => 'مرفوض', 'cancelled' => 'ملغى', 'closed' => 'مغلق'],
    'return_action' => ['approve' => 'موافقة', 'reject' => 'رفض', 'request_info' => 'طلب معلومات', 'resume' => 'إعادة للمراجعة', 'mark_received' => 'تعيين كمستلم', 'inspect' => 'إكمال الفحص', 'refund' => 'استرداد', 'ship_exchange' => 'شحن الاستبدال', 'complete_exchange' => 'إكمال الاستبدال', 'close' => 'إغلاق'],
    'return_type' => ['return' => 'إرجاع', 'exchange' => 'استبدال'],
    'inspection_result' => ['accepted' => 'مقبول', 'failed' => 'مرفوض'],
    'return_event_type' => ['status' => 'حالة', 'note' => 'ملاحظة', 'message' => 'رسالة'],
    'return_block' => ['order_not_returnable' => 'لم يُشحن بعد', 'return_window_expired' => 'انتهت مدة الإرجاع', 'nothing_returnable' => 'لا يوجد ما يمكن إرجاعه'],
    'return_media_kind' => ['photo' => 'صورة', 'video' => 'فيديو', 'inspection' => 'فحص'],
    'return_shipping_payer' => ['customer_unless_fault' => 'العميل، إلا عند التلف', 'customer' => 'العميل', 'store' => 'المتجر'],
    'refund_status' => ['pending' => 'قيد الانتظار', 'processing' => 'قيد المعالجة', 'completed' => 'مكتمل', 'failed' => 'فشل', 'rejected' => 'مرفوض'],
    'refund_source' => ['cancellation' => 'إلغاء', 'return' => 'إرجاع', 'manual' => 'يدوي'],
    'refund_method' => ['original_payment' => 'وسيلة الدفع الأصلية', 'manual' => 'يدوي'],
];
