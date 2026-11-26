<?php

return [
    'errors' => [
        'store_missing' => 'The store could not be found.',
        'crashed' => 'The translation stopped unexpectedly. Retry to continue where it stopped.',
        'items_failed' => ':count item could not be translated. Retry to translate it again.|:count items could not be translated. Retry to translate them again.',
        'placeholders' => 'The translation changed placeholders, links or HTML tags of the text.',
    ],
    'notifications' => [
        'translation_completed' => [
            'title' => ':language translation is ready',
            'body' => 'Your store has been translated into :language. The language is now live in your storefront language switcher.',
        ],
        'translation_failed' => [
            'title' => ':language translation needs attention',
            'body' => 'The AI translation into :language did not finish. Open Settings › Languages and retry — finished items are kept.',
        ],
    ],
];
