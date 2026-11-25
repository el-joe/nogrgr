<?php

return [
    'contact' => [
        'title' => 'New contact message from :name',
        'body' => ':name (:email) wrote: “:subject”.',
        'mail_subject' => 'Contact form: :subject',
        'from' => 'From: :name <:email>',
        'phone' => 'Phone: :phone',
        'subject_line' => 'Subject: :subject',
    ],
    'newsletter' => [
        'subject' => 'Confirm your :name newsletter subscription',
        'greeting' => 'Hello!',
        'body' => 'Thanks for subscribing to the :name newsletter. Please confirm your e-mail address to start receiving our updates.',
        'confirm' => 'Confirm subscription',
        'ignore' => 'If you did not request this, just ignore this e-mail.',
        'unsubscribe' => 'To unsubscribe at any time, open: :url',
    ],
];
