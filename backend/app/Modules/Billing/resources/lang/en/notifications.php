<?php

return [
    'greeting' => 'Hello :name,',
    'open_billing' => 'Open billing',
    'invoice_issued' => [
        'title' => 'Invoice :number issued',
        'body' => ':store was invoiced :total.',
        'vendor_subject' => 'New invoice :invoice_number',
        'vendor_body' => 'Invoice :invoice_number for :invoice_total was issued for :store_name and is due on :invoice_due_date.',
    ],
    'invoice_paid' => [
        'title' => 'Invoice :number paid',
        'body' => ':store paid :total.',
        'vendor_subject' => 'Payment received for invoice :invoice_number',
        'vendor_body' => 'Thank you. We received :invoice_total for invoice :invoice_number.',
    ],
    'payment_failed' => [
        'title' => 'Payment :reference failed',
        'body' => 'A :amount payment from :store via :gateway failed.',
        'vendor_subject' => 'Your payment did not go through',
        'vendor_body' => 'Your :payment_amount payment via :payment_gateway for invoice :invoice_number failed. Please try again or use another payment method.',
    ],
    'payment_refunded' => [
        'title' => 'Payment :reference refunded',
        'body' => ':amount was refunded to :store via :gateway.',
        'vendor_subject' => 'Refund issued',
        'vendor_body' => 'We refunded :payment_amount via :payment_gateway for invoice :invoice_number.',
    ],
    'subscription_activated' => [
        'title' => 'Subscription activated',
        'body' => ':store is now on the :plan plan until :date.',
        'vendor_subject' => 'Your :plan_name plan is active',
        'vendor_body' => 'Your :plan_name subscription for :store_name is active until :subscription_ends_at.',
    ],
    'subscription_past_due' => [
        'title' => 'Subscription past due',
        'body' => 'The :plan plan of :store could not be renewed.',
        'vendor_subject' => 'Your plan renewal failed',
        'vendor_body' => 'We could not renew the :plan_name plan of :store_name. Pay the open invoice to keep your store online.',
    ],
    'subscription_cancelled' => [
        'title' => 'Subscription cancelled',
        'body' => 'The :plan plan of :store was cancelled (ends :date).',
    ],
    'subscription_renewed' => [
        'title' => 'Subscription renewed',
        'body' => 'The :plan plan of :store was renewed until :date.',
    ],
    'invoice_voided' => [
        'title' => 'Invoice :number voided',
        'body' => 'The :total invoice of :store was voided.',
    ],
    'subscription_expired' => [
        'title' => 'Subscription expired',
        'body' => 'The :plan plan of :store has expired.',
        'vendor_subject' => 'Your subscription has expired',
        'vendor_body' => 'The :plan_name plan of :store_name has expired. Renew it to reactivate your store.',
    ],
];
