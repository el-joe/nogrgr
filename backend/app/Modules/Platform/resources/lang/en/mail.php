<?php

return [
    'registration_receipt' => [
        'subject' => 'Your :app payment receipt',
        'greeting' => 'Payment received',
        'intro' => 'Thank you! We received the payment for the :plan plan (:term).',
        'amount' => 'Amount paid: :amount',
        'reference' => 'Payment reference: :reference',
        'next' => 'Your plan starts when you finish setting up your store. The invoice is in your store panel.',
    ],
    'registration_link' => [
        'subject' => 'Complete your :app store registration',
        'greeting' => 'Welcome!',
        'intro' => 'Your e-mail is almost confirmed. Use the button below to create your account and set up your store.',
        'action' => 'Complete registration',
        'expiry' => 'This link expires in :hours hours.',
        'ignore' => 'If you did not sign up, you can ignore this e-mail.',
    ],
    'welcome' => [
        'subject' => ':store is ready',
        'greeting' => 'Your store is live!',
        'intro' => 'We finished setting up :store: its database, languages and your owner account are ready.',
        'address' => 'Store address: :url',
        'action' => 'Sign in to your vendor panel',
        'outro' => 'Sign in with this e-mail address.',
    ],
    'compliance' => [
        'greeting' => 'Hello,',
        'subject_verified' => ':store: compliance verified',
        'subject_needs_action' => ':store: compliance needs your action',
        'subject_rejected' => ':store: compliance rejected',
        'subject_needs_review' => ':store: compliance under review',
        'intro_verified' => 'Good news: the compliance documents of :store are verified.',
        'intro_needs_action' => 'We reviewed the compliance documents of :store and need a few changes before we can verify them.',
        'intro_rejected' => 'We could not accept the compliance documents of :store.',
        'intro_needs_review' => 'The compliance documents of :store are under review.',
        'note_heading' => 'Note from our team:',
        'outro' => 'Reply to this e-mail or contact our support team if you have any questions.',
    ],
];
