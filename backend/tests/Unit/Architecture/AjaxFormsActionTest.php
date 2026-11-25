<?php

/*
| QA-P5-004: a form field named "action" (refund retry / complete / reject modals,
| payouts) shadows HTMLFormElement.action, so the shared data-ajax handler posted to
| "/admin/[object HTMLInputElement]". The handler must read the attribute instead.
*/

it('submits data-ajax forms to the action attribute, not the shadowable form.action property', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/core/ajax-forms.js');

    expect($source)->toContain("form.getAttribute('action')")
        ->and($source)->not->toMatch('/http\.request\([^)]*\bform\.action\b/');
});

it('keeps the refund action modal posting its "action" field to the refund action route', function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 3).'/app/Modules/AfterSales/resources/views/refunds/_action-form.blade.php');

    expect($view)->toContain('data-ajax')
        ->and($view)->toContain('name="action"');
});
