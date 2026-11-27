<?php

/*
| RQA-ADM-001: at 375px many admin pages were cut off (left edge in RTL, right in LTR)
| because implicit grid tracks inside the panel <main> kept `auto` sizing and grid items
| kept min-width:auto, so one wide child (page header, form row, card, chart canvas)
| widened the track past the content box. The segmented status-tab strip did not scroll,
| so tabs past 343px were unreachable. These rules must stay in the shared panel CSS
| and components (admin, vendor and affiliate panels all use them).
*/

$root = dirname(__DIR__, 3);

it('keeps implicit panel grid tracks and items shrinkable', function () use ($root): void {
    $css = (string) file_get_contents($root.'/resources/css/panels/base.css');

    expect($css)
        ->toContain(":where(#main .grid:not([class*='grid-flow-col'], [class*='auto-cols'])) {\n        grid-template-columns: minmax(0, 1fr);")
        ->toContain(":where(#main .grid > *) {\n        min-width: 0;")
        ->toContain(":where(#main canvas) {\n        max-width: 100%;");
});

it('renders the panel content inside #main so the grid rules apply', function () use ($root): void {
    $layout = (string) file_get_contents($root.'/resources/views/components/layouts/panel.blade.php');

    expect($layout)->toContain('<main id="main"');
});

it('lets every tab strip variant scroll sideways', function () use ($root): void {
    $tabs = (string) file_get_contents($root.'/resources/views/components/ui/tabs.blade.php');

    expect($tabs)
        ->toContain('aria-label="{{ $label ?? __(\'ui.tabs\') }}" data-tabs-scroll @class([')
        ->toContain("'ui-tabs-segmented inline-flex max-w-full gap-1 overflow-x-auto")
        ->toContain("'inline-flex h-8 shrink-0 items-center");
});

it('caps chart canvases to their card', function () use ($root): void {
    $chart = (string) file_get_contents($root.'/resources/views/components/ui/chart-card.blade.php');

    expect($chart)->toContain('<canvas data-chart="{{ $id }}" class="max-w-full"');
});
