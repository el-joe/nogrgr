<?php

/*
| QA-FINAL-004: the Ecommet home scrolled sideways — by 176 px at 1440 (the single
| implicit grid track grew with the "why choose us" row and the deals tabs) and by
| ~600 px at 375 (the deals rail is overflow-visible on phones for the edge peek, and
| nothing above it clipped). Grid tracks must be minmax(0, 1fr) and the deals section
| clips horizontally below lg.
*/

it('keeps the Ecommet home grid tracks shrinkable', function (): void {
    $home = (string) file_get_contents(dirname(__DIR__, 3).'/app/Modules/Storefront/resources/views/themes/ecommet/pages/home.blade.php');

    expect($home)->toContain('class="eco-container mt-4 grid grid-cols-1 gap-6');
});

it('clips the deals rail on phones and keeps its track shrinkable', function (): void {
    $deals = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/components/storefront/section/deals.blade.php');

    expect($deals)->toContain("'grid grid-cols-1 gap-3 max-lg:overflow-x-clip lg:gap-4'");
});
