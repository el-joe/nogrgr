<?php

/*
| QA-P5-017: list pages keep their tab / status scope in a hidden data-filter input
| (vendor coupons and flash sales "My coupons" tab, payouts, settlements, returns...).
| hasActiveFilters() counted it, so an empty tab always showed "No results match your
| filters" + "Clear filters" instead of the tab's own empty state and create button.
*/

it('does not count an unlabelled hidden scope field as an active filter', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/resources/js/panels/ui/filters.js');

    expect($source)->toMatch("/export function hasActiveFilters[\\s\\S]*?field\\.type === 'hidden' && !field\\.hasAttribute\\('data-filter-label'\\)/");
});

it('keeps the vendor promotion tabs scoped by an unlabelled hidden filter', function (string $view): void {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/app/Modules/Commerce/resources/views/vendor/'.$view.'/index.blade.php');

    expect($source)->toMatch('/<input type="hidden" name="tab" value="\{\{ \$tab \}\}" data-filter>/');
})->with(['coupons', 'flash-sales']);
