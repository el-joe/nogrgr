<?php

return [
    'fields' => [
        'rating' => 'Rating',
        'title' => 'Review title',
        'body' => 'Your review',
    ],
    'messages' => [
        'published' => 'Thank you! Your review is now live.',
        'pending' => 'Thank you! Your review will appear once the store approves it.',
    ],
    'errors' => [
        'guest' => 'Sign in to write a review.',
        'not_purchased' => 'You can review this product after it is delivered to you.',
        'already_reviewed' => 'You have already reviewed this product.',
        'line_not_found' => 'This item is not part of the order.',
        'product_not_found' => 'This product is not available.',
    ],
];
