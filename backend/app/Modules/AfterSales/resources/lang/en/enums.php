<?php

return [
    'return_status' => ['pending' => 'Pending review', 'awaiting_info' => 'Awaiting info', 'approved' => 'Approved', 'item_received' => 'Item received', 'inspected' => 'Inspection', 'refunded' => 'Refunded', 'exchange_shipped' => 'Exchange shipped', 'exchanged' => 'Exchanged', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'closed' => 'Closed'],
    'return_action' => ['approve' => 'Approve', 'reject' => 'Reject', 'request_info' => 'Request more info', 'resume' => 'Back to review', 'mark_received' => 'Mark received', 'inspect' => 'Complete inspection', 'refund' => 'Refund', 'ship_exchange' => 'Ship exchange', 'complete_exchange' => 'Complete exchange', 'close' => 'Close'],
    'return_type' => ['return' => 'Return', 'exchange' => 'Exchange'],
    'inspection_result' => ['accepted' => 'Accepted', 'failed' => 'Failed'],
    'return_event_type' => ['status' => 'Status', 'note' => 'Note', 'message' => 'Message'],
    'return_media_kind' => ['photo' => 'Photo', 'video' => 'Video', 'inspection' => 'Inspection'],
    'return_shipping_payer' => ['customer_unless_fault' => 'Customer, unless damaged', 'customer' => 'Customer', 'store' => 'Store'],
    'refund_status' => ['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed', 'rejected' => 'Rejected'],
    'refund_source' => ['cancellation' => 'Cancellation', 'return' => 'Return', 'manual' => 'Manual'],
    'refund_method' => ['original_payment' => 'Original payment', 'manual' => 'Manual'],
];
