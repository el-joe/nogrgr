<?php

/*
| RQA-VEN-013: a JSON save answering {message, redirect} showed the toast and navigated
| at once, so the message was lost (General, Languages, Return policy, ...). http.js now
| queues it in sessionStorage and the shared bindToasts() (every surface boots it, vendor
| and admin panels included) shows it on the page the redirect opens.
*/

$read = fn (string $path): string => (string) file_get_contents(dirname(__DIR__, 3).'/'.$path);

it('queues the success toast when the answer redirects', function () use ($read): void {
    $http = $read('resources/js/core/http.js');

    expect($http)->toContain("import { notify, queueToast } from './notify';")
        ->and($http)->toMatch("/if \\(navigates\\) \\{\\s*queueToast\\('success', payload\\.message\\);\\s*\\} else \\{\\s*notify\\.success\\(payload\\.message\\);/")
        ->and($http)->toContain('window.location.assign(payload.redirect);');
});

it('shows queued toasts when the next page boots', function () use ($read): void {
    $notify = $read('resources/js/core/notify.js');

    expect($notify)->toContain('export function queueToast(')
        ->and($notify)->toContain('window.sessionStorage.setItem(PENDING_KEY')
        ->and($notify)->toMatch('/export function bindToasts\(root = document\) \{\s*if \(root === document\) \{\s*flushQueuedToasts\(\);/')
        ->and($read('resources/js/core/index.js'))->toContain('bindToasts(document);')
        ->and($read('resources/js/panels/core.js'))->toContain('boot(');
});
