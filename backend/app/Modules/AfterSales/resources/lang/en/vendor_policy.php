<?php

return [
    'title' => 'Return policy',
    'subtitle' => 'You can be stricter than the Nogrgr default (:days days), not looser.',
    'save' => 'Save',
    'saved' => 'Return policy saved.',
    'using_default' => 'Your store follows the Nogrgr default policy until you save your own.',
    'fields' => [
        'window' => 'Return window (days)',
        'window_help' => 'Max :days (platform default)',
        'fee' => 'Restocking fee (%)',
        'fee_help' => 'Min :fee% (platform default). Not charged when the reason is your fault.',
        'categories' => 'Non-returnable',
        'categories_placeholder' => 'Choose categories',
        'categories_help' => 'Categories the platform marks non-returnable always stay on the list.',
        'exchanges' => 'Allow exchanges',
        'exchanges_off' => 'Exchanges are switched off by the platform.',
        'text' => 'Policy text (shown on storefront)',
        'text_in' => 'Policy text — :language',
        'text_help' => 'Shown to shoppers on the return request form.',
    ],
    'errors' => [
        'window' => 'The return window cannot be longer than the platform default of :days days.',
        'fee' => 'The restocking fee cannot be lower than the platform default of :fee%.',
    ],
];
