<?php

return [
    'tabs' => [
        'platform' => 'Platform support',
        'customers' => 'Customers',
    ],
    'title' => 'Customer tickets',
    'subtitle' => 'Questions your customers sent from the app or your store account pages.',
    'list' => 'Customer tickets',
    'search' => 'Search reference, subject or customer',
    'status_filter' => 'Status',
    'active' => 'Active',
    'customer' => 'Customer',
    'order' => 'Order :number',
    'topic' => 'Topic',
    'new_badge' => 'New',
    'opened_at' => 'Opened :date',
    'empty' => [
        'title' => 'No customer tickets yet',
        'description' => 'When a customer contacts you from their account or the app, the ticket shows here.',
        'filtered' => 'No customer tickets match these filters.',
    ],
    'actions' => [
        'resolve' => 'Mark resolved',
        'resolve_title' => 'Resolve ticket :reference?',
        'resolve_message' => 'The customer is told the issue is solved. A new message from them reopens it.',
        'close' => 'Close',
        'close_title' => 'Close ticket :reference?',
        'close_message' => 'The customer can no longer reply. You can reopen it later.',
        'reopen' => 'Reopen',
        'reopen_title' => 'Reopen ticket :reference?',
        'reopen_message' => 'The ticket becomes active again and the customer can reply.',
    ],
    'sent' => 'Reply sent to the customer.',
    'status_saved' => 'Ticket :reference is now :status.',
    'notifications' => [
        'ticket_replied' => [
            'title' => 'New reply on your ticket :reference',
            'body' => ':store replied to “:subject”.',
        ],
        'ticket_resolved' => [
            'title' => 'Ticket :reference resolved',
            'body' => ':store marked “:subject” as resolved. Reply if you still need help.',
        ],
        'ticket_closed' => [
            'title' => 'Ticket :reference closed',
            'body' => ':store closed “:subject”.',
        ],
        'ticket_reopened' => [
            'title' => 'Ticket :reference reopened',
            'body' => ':store reopened “:subject”. You can reply again.',
        ],
    ],
    'mail' => [
        'greeting' => 'Hi :name,',
        'cta' => 'View ticket',
    ],
    'api' => [
        'opened' => 'Ticket :reference sent. The store will reply here.',
        'replied' => 'Message sent.',
        'closed' => 'Ticket :reference closed.',
    ],
    'errors' => [
        'order' => 'Pick one of your own orders.',
    ],
];
