<?php

return [
    'support_request' => [
        'title' => 'New support request :ticket',
        'body' => ':name (:email) wrote about ":subject".',
    ],
    'review_pending' => [
        'title' => 'A review is waiting for approval',
        'body' => ':author rated ":product" :rating/5. Approve it to publish it on your store.',
    ],
    'review_published' => [
        'title' => 'New product review',
        'body' => ':author rated ":product" :rating/5.',
    ],
    'customer' => [
        'review_published' => ['title' => 'Your review is live', 'body' => 'Thank you! Your review of :product is now published on :store.'],
        'review_rejected' => ['title' => 'Your review was not published', 'body' => 'Your review of :product did not meet the review guidelines of :store.'],
    ],
];
