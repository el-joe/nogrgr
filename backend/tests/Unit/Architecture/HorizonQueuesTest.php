<?php

use Symfony\Component\Finder\Finder;

/*
| RQA-VEN-002: every queue the app dispatches to — onQueue('x'), public $queue = 'x',
| const QUEUE = 'x' — is run by the Horizon supervisor, otherwise those jobs (store
| provisioning, notifications, mail) never run in production.
*/

it('runs every queue used in app/ on the Horizon supervisor', function (): void {
    $root = dirname(__DIR__, 3);
    $config = require $root.'/config/horizon.php';
    $supervised = collect($config['defaults'])->pluck('queue')->flatten()->unique()->all();

    $patterns = [
        '/->onQueue\(\s*[\'"]([\w.-]+)[\'"]\s*\)/',
        '/\$queue\s*=\s*[\'"]([\w.-]+)[\'"]/',
        '/const\s+(?:string\s+)?QUEUE\s*=\s*[\'"]([\w.-]+)[\'"]/',
    ];

    $used = [];

    foreach ((new Finder)->files()->in($root.'/app')->name('*.php') as $file) {
        $contents = $file->getContents();

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches) > 0) {
                foreach ($matches[1] as $queue) {
                    $used[$queue][] = $file->getRelativePathname();
                }
            }
        }
    }

    expect($used)->toHaveKeys(['provisioning', 'notifications', 'mail']);

    $missing = array_diff_key($used, array_flip($supervised));

    expect($missing)->toBe([], 'Queues used in app/ but not run by config/horizon.php: '.json_encode($missing));
});

it('lists the supervisor queues in priority order', function (): void {
    $config = require dirname(__DIR__, 3).'/config/horizon.php';

    expect($config['defaults']['supervisor-1']['queue'])->toBe(['provisioning', 'default', 'notifications', 'mail']);
});
