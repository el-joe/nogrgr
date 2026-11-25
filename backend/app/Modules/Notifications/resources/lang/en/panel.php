<?php

return [
    'title' => 'Notifications',
    'subtitle' => 'Everything that needs your attention, in one place.',
    'tabs' => [
        'inbox' => 'Inbox',
        'preferences' => 'Preferences',
    ],
    'vendor_subtitle' => 'View all notifications from the admin team including order updates and product assignments.',
    'unread_count' => '{0} All read|{1} 1 unread|[2,*] :count unread',
    'inbox' => [
        'all' => 'All',
        'filter_label' => 'Filter notifications by type',
        'state' => 'Status',
        'columns' => [
            'notification' => 'Notification',
            'received' => 'Received',
            'mark_all_vendor' => 'Mark all as read',
            'vendor_empty_description' => 'Replies to your requests, order updates and product assignments will show up here.',
        ],
        'new' => 'New',
        'open' => 'Open',
        'mark_read' => 'Mark as read',
        'mark_unread' => 'Mark as unread',
        'mark_all' => 'Mark all read',
        'delete' => 'Delete',
        'delete_title' => 'Delete this notification?',
        'delete_message' => 'It will be removed from your inbox. This cannot be undone.',
        'marked_read' => 'Notification marked as read.',
        'marked_unread' => 'Notification marked as unread.',
        'marked_all' => '{0} You are all caught up.|{1} 1 notification marked as read.|[2,*] :count notifications marked as read.',
        'deleted' => 'Notification deleted.',
        'empty_title' => 'You are all caught up',
        'empty_description' => 'New orders, vendor updates, payments, requests and system events will show up here.',
        'live' => 'New notification',
    ],
    'preferences' => [
        'title' => 'Preferences',
        'subtitle' => 'Choose how each event reaches you.',
        'event' => 'Event',
        'save' => 'Save preferences',
        'saved' => 'Notification preferences saved.',
        'channel_help' => [
            'in_app' => 'Inbox and the bell in the top bar.',
            'email' => 'Sent to your account email.',
            'push' => 'Instant pop-up while the panel is open.',
        ],
        'toggle' => ':channel for :category',
    ],
    'mail' => [
        'open' => 'Open in the panel',
    ],
];
