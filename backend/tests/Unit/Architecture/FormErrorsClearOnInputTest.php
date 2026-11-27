<?php

/*
| RQA-ADM-011: an inline 422 error stayed visible after the user corrected the field
| (label / coupon modals, branch form...) until the next submit. The shared form-error
| module clears a field's message + aria-invalid on input / change and is bound by the
| core boot every panel (admin, vendor, affiliate, owner), remote modal and storefront uses.
*/

it('wires clear-on-input into the shared form-error module', function (): void {
    $root = dirname(__DIR__, 3);
    $module = file_get_contents($root.'/resources/js/core/form-errors.js');

    expect($module)
        ->toContain('export function bindClearOnInput(')
        ->toContain('export function clearFieldError(')
        ->toContain("['input', 'change', 'countrychange']")
        ->toContain("removeAttribute('aria-invalid')")
        ->toContain('[data-error-for]')
        ->toContain('[data-editor-input]')
        ->toContain('_e164')
        ->toContain('[role="tab"][data-error]')
        ->toContain('delete tab.dataset.error');
});

it('binds clear-on-input in the core boot used by every surface', function (): void {
    $root = dirname(__DIR__, 3);
    $core = file_get_contents($root.'/resources/js/core/index.js');

    expect($core)->toContain("import { bindClearOnInput } from './form-errors';")
        ->and($core)->toMatch('/function boot\([^)]*\)\s*\{.*bindClearOnInput\(document\);/s');

    foreach (['admin.js', 'vendor.js', 'affiliate.js', 'owner.js'] as $entry) {
        expect(file_get_contents($root.'/resources/js/'.$entry))->toContain("import { bootPanel } from './panels/core'");
    }

    expect(file_get_contents($root.'/resources/js/panels/core.js'))->toContain('boot(() => {');
});

it('keeps the select2 bridge that turns jQuery changes into native change events', function (): void {
    $select2 = file_get_contents(dirname(__DIR__, 3).'/resources/js/panels/select2.js');

    expect($select2)->toContain("element.dispatchEvent(new Event('change', { bubbles: true }))");
});
