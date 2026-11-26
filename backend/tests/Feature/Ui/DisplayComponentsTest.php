<?php

use App\Shared\Support\Icon;

/*
| x-ui::* buttons, data display, feedback, overlays and data table markup.
*/

it('renders buttons with variants, sizes, loading and disabled states', function (): void {
    $this->blade('<x-ui::button variant="danger" size="lg" icon="trash">Delete</x-ui::button>')
        ->assertSee('ui-btn-danger', false)
        ->assertSee('h-12', false)
        ->assertSee('data-icon="trash"', false);

    $this->blade('<x-ui::button loading>Save</x-ui::button>')
        ->assertSee('data-loading', false)
        ->assertSee('aria-busy="true"', false)
        ->assertSee(__('ui.saving'));

    $this->blade('<x-ui::button href="/admin" disabled>Go</x-ui::button>')
        ->assertSee('<button', false)
        ->assertSee('disabled', false);

    $this->blade('<x-ui::icon-button icon="more" label="More actions" />')
        ->assertSee('aria-label="More actions"', false)
        ->assertSee('title="More actions"', false);
});

it('maps statuses to badge tones and translated labels', function (string $status, string $tone): void {
    $classes = ['success' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger', 'info' => 'text-info', 'brand' => 'text-ink-brand', 'neutral' => 'text-ink-secondary'];

    $this->blade('<x-ui::status :status="$status" />', ['status' => $status])
        ->assertSee($classes[$tone], false)
        ->assertSee(__('ui.status.'.$status));
})->with([
    ['draft', 'neutral'],
    ['featured', 'brand'],
    ['delivered', 'success'],
    ['processing', 'warning'],
    ['refunded', 'danger'],
    ['shipped', 'info'],
]);

it('renders KPI cards with trend, sparkline and the highlight gradient', function (): void {
    $this->blade('<x-ui::kpi-card label="Orders" value="18,402" icon="cart" :delta="8.1" comparison="vs prev. 30d" :spark="[1, 2, 3]" />')
        ->assertSee('Orders')
        ->assertSee('18,402')
        ->assertSee('+8.1%')
        ->assertSee('bg-success-bg', false)
        ->assertSee('<polyline', false);

    $this->blade('<x-ui::kpi-card label="Refunds" value="2.1%" :delta="-0.6" highlight />')
        ->assertSee('bg-linear-to-br', false)
        ->assertSee('−0.6%');
});

it('isolates the KPI value as LTR so an Arabic currency string keeps its symbol order (QA-P5-018)', function (): void {
    $this->blade('<x-ui::kpi-card label="Balance" :value="$value" />', ['value' => "\u{061C}-\u{200F}١١٠٫٨٧ US$"])
        ->assertSee('<bdi dir="ltr">', false);
});

it('renders chart cards with a JSON config island, ranges and an empty state', function (): void {
    $ranges = ['7d' => ['labels' => ['Mon'], 'series' => [['label' => 'Gross', 'data' => [1], 'tone' => 'brand']]], '30d' => ['labels' => ['1'], 'series' => [['label' => 'Gross', 'data' => [2]]]]];

    $this->blade('<x-ui::chart-card id="rev" title="Revenue" :ranges="$ranges" range="30d" />', ['ranges' => $ranges])
        ->assertSee('data-chart="rev"', false)
        ->assertSee('<script type="application/json" data-chart-config="rev">', false)
        ->assertSee('data-chart-range="7d"', false)
        ->assertSee('aria-pressed="true"', false);

    $this->blade('<x-ui::chart-card id="empty" title="Orders" empty />')
        ->assertDontSee('data-chart="empty"', false)
        ->assertSee(__('ui.chart.empty_title'));
});

it('renders underline tabs with panels synced to the query string, and link tabs', function (): void {
    $this->blade('<x-ui::tabs id="t" :items="[\'a\' => \'General\', \'b\' => [\'label\' => \'Logs\', \'count\' => 3]]"><x-ui::tab-panel tabs="t" key="a" active>A</x-ui::tab-panel></x-ui::tabs>')
        ->assertSee('data-tabs-param="tab"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('aria-controls="t-b"', false)
        ->assertSee('role="tabpanel"', false);

    $this->blade('<x-ui::tabs id="l" variant="segmented" :items="[\'x\' => [\'label\' => \'X\', \'href\' => \'/x\']]" active="x" />')
        ->assertSee('href="/x"', false)
        ->assertSee('aria-current="page"', false)
        ->assertDontSee('role="tablist"', false);
});

it('renders the page header with breadcrumb, status and actions', function (): void {
    $this->blade(<<<'BLADE'
        <x-ui::page-header title="Products" subtitle="All products" :breadcrumbs="['Catalog' => '/admin/catalog', 'Products' => null]">
            <x-slot:status><x-ui::status status="active" /></x-slot:status>
            <x-slot:actions><x-ui::button>Add product</x-ui::button></x-slot:actions>
        </x-ui::page-header>
        BLADE)
        ->assertSee('<h1', false)
        ->assertSee('href="/admin/catalog"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('Add product');
});

it('renders empty states, skeletons and server toasts', function (): void {
    $this->blade('<x-ui::empty-state variant="no-results" />')->assertSee(__('ui.empty.no_results_title'));
    $this->blade('<x-ui::empty-state variant="error" />')->assertSee('role="alert"', false);
    $this->blade('<x-ui::skeleton :lines="3" />')->assertSee('ui-skeleton', false);
    $this->blade('<x-ui::toast tone="warning" title="Careful" message="Check it" />')
        ->assertSee('data-toast-timeout="8000"', false)
        ->assertSee('Careful');
    $this->blade('<x-ui::toast tone="error" title="Failed" />')
        ->assertSee('data-toast-timeout="0"', false)
        ->assertSee('role="alert"', false);
});

it('renders modal (bottom sheet ready), drawer and the confirm dialog accessibly', function (): void {
    $this->blade('<x-ui::modal id="m" title="Add zone" size="sm">Body<x-slot:footer><x-ui::button>Save</x-ui::button></x-slot:footer></x-ui::modal>')
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee('aria-labelledby="m-title"', false)
        ->assertSee('data-sheet', false)
        ->assertSee('max-w-[400px]', false)
        ->assertSee('data-overlay-close', false);

    $this->blade('<x-ui::drawer id="d" title="Filters">Body</x-ui::drawer>')
        ->assertSee('data-drawer', false);

    $this->blade('<x-ui::confirm />')
        ->assertSee('id="ui-confirm"', false)
        ->assertSee('role="alertdialog"', false)
        ->assertSee('data-confirm-accept', false)
        ->assertSee('data-confirm-require', false);
});

it('renders dropdown menus, row actions and the notification dropdown', function (): void {
    $this->blade(<<<'BLADE'
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" href="/edit">Edit</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="trash" tone="danger" data-confirm="Sure?">Delete</x-ui::dropdown-item>
        </x-ui::row-actions>
        BLADE)
        ->assertSee('data-dropdown-trigger', false)
        ->assertSee('role="menu"', false)
        ->assertSee('role="menuitem"', false)
        ->assertSee('data-tone="danger"', false)
        ->assertSee('data-confirm="Sure?"', false);

    $this->blade('<x-ui::notification-dropdown :items="$items" />', ['items' => [['title' => 'New order', 'meta' => 'Store', 'time' => '2m', 'unread' => true]]])
        ->assertSee('New order')
        ->assertSee('data-notification-count', false)
        ->assertSee(trans_choice('ui.notifications.new', 1, ['count' => 1]));

    $this->blade('<x-ui::notification-dropdown :items="[]" />')
        ->assertSee(__('ui.notifications.empty_title'));
});

it('renders the data table shell with selection, states, pagination and bulk bar', function (): void {
    $this->blade(<<<'BLADE'
        <x-ui::datatable id="products" url="/admin/products/data" selectable :columns="[['data' => 'name', 'title' => 'Name'], ['data' => 'price', 'title' => 'Price', 'align' => 'end']]">
            <x-slot:filters><x-ui::filters-bar target="products" :columns="['Name', 'Price']" /></x-slot:filters>
            <x-slot:bulk><x-ui::button data-bulk-action data-url="/bulk">Activate</x-ui::button></x-slot:bulk>
        </x-ui::datatable>
        BLADE)
        ->assertSee('data-datatable', false)
        ->assertSee('data-table-columns=', false)
        ->assertSee('data-dt-select-all', false)
        ->assertSee('scope="col"', false)
        ->assertSee('data-dt-skeleton', false)
        ->assertSee('data-dt-empty', false)
        ->assertSee('data-dt-empty-filtered', false)
        ->assertSee('data-dt-error', false)
        ->assertSee('data-dt-pagination', false)
        ->assertSee('data-dt-bulk', false)
        ->assertSee('data-filters="products"', false)
        ->assertSee('data-dt-column="1"', false);
});

it('inlines every exported icon', function (): void {
    foreach (Icon::names() as $name) {
        $this->blade('<x-ui::icon :name="$name" />', ['name' => $name])->assertSee('data-icon="'.$name.'"', false);
    }
});
