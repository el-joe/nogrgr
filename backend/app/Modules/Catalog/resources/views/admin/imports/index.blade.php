{{--
    Admin › Catalog › Products › Import (ADM-013, Figma Admin Panel 65:22120): stepper,
    live "Import in progress" card (bars + counters), Source card with pause / resume /
    cancel, unmapped-categories alert, the run's log (server-side, errors via the
    result filter) and the runs history. Empty states: driver not configured (link to
    Integrations) and never imported. Run import modal with the source preview.
--}}
@php
    use App\Modules\Catalog\Enums\ImportLogResult;
    use App\Modules\Catalog\Enums\ImportStatus;

    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('catalog.imports.manage');
    $canIntegrations = (bool) $admin?->can('integrations.view');
    $steps = [
        'source' => $configured,
        'mapping' => $configured && $mappings > 0 && $unmapped === 0,
        'preview' => $configured && $run !== null,
        'import' => $run?->status === ImportStatus::Completed,
    ];
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::imports.title')" data-page="admin-catalog-import">
    <div class="grid gap-6"
        data-import-page
        @if ($run) data-progress-url="{{ route('admin.catalog.imports.progress', $run) }}" data-active="{{ $progress['active'] ? '1' : '0' }}" @endif>
        <x-ui::page-header
            :title="__('catalog::imports.title')"
            :subtitle="__('catalog::imports.subtitle')"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::products.title') => route('admin.catalog.products.index'), __('catalog::imports.breadcrumb') => null]"
        >
            @if ($canManage && $configured)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="layers" :href="route('admin.catalog.imports.categories', ['driver' => $driver->value])">{{ __('catalog::imports.map_categories') }}</x-ui::button>
                    <x-ui::button icon="download" data-overlay-open="run-import-modal" data-import-open :disabled="(bool) $progress && $progress['active']">{{ __('catalog::imports.run') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @include('catalog::admin.imports._stepper', ['steps' => $steps])

        @if (! $configured)
            <x-ui::card>
                <x-ui::empty-state icon="link" :title="__('catalog::imports.not_configured.title')" :description="__('catalog::imports.not_configured.description', ['driver' => $driver->label()])">
                    @if ($canIntegrations)
                        <x-ui::button icon="settings" :href="route('admin.integrations.show', $driver->integrationSection())">{{ __('catalog::imports.not_configured.action') }}</x-ui::button>
                    @else
                        <p class="text-[13px] text-ink-muted">{{ __('catalog::imports.not_configured.no_access') }}</p>
                    @endif
                </x-ui::empty-state>
            </x-ui::card>
        @else
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
                <div class="grid min-w-0 gap-6">
                    @if ($run)
                        @include('catalog::admin.imports._progress', ['run' => $run, 'progress' => $progress])

                            <x-ui::datatable
                                id="import-log"
                                :url="route('admin.catalog.imports.logs', $run)"
                                :columns="[
                                    ['data' => 'created_at', 'title' => __('catalog::imports.log.columns.time'), 'searchable' => false],
                                    ['data' => 'item', 'title' => __('catalog::imports.log.columns.item'), 'searchable' => false],
                                    ['data' => 'result', 'title' => __('catalog::imports.log.columns.result'), 'searchable' => false],
                                    ['data' => 'message', 'title' => __('catalog::imports.log.columns.message'), 'orderable' => false, 'searchable' => false],
                                ]"
                                :order="[[0, 'desc']]"
                                :empty-title="__('catalog::imports.log.empty_title')"
                                :empty-description="__('catalog::imports.log.empty_description')"
                            >
                                <x-slot:filters>
                                    <div class="grid gap-3">
                                        <h2 class="text-base font-semibold text-ink">{{ __('catalog::imports.log.title') }}</h2>
                                        <x-ui::filters-bar target="import-log" :search-placeholder="__('catalog::imports.log.search')">
                                            <x-ui::select name="result" :label="__('catalog::imports.log.result')" label-hidden :options="ImportLogResult::options()" :placeholder="__('catalog::imports.log.result')" :search="false" allow-clear data-filter :data-filter-label="__('catalog::imports.log.result')" class="w-40" />
                                        </x-ui::filters-bar>
                                    </div>
                                </x-slot:filters>
                            </x-ui::datatable>
                    @else
                        <x-ui::card>
                            <x-ui::empty-state icon="download" :title="__('catalog::imports.never.title')" :description="__('catalog::imports.never.description', ['driver' => $driver->label()])">
                                @if ($canManage)
                                    <x-ui::button variant="secondary" icon="layers" :href="route('admin.catalog.imports.categories', ['driver' => $driver->value])">{{ __('catalog::imports.map_categories') }}</x-ui::button>
                                    <x-ui::button icon="download" data-overlay-open="run-import-modal" data-import-open>{{ __('catalog::imports.run') }}</x-ui::button>
                                @endif
                            </x-ui::empty-state>
                        </x-ui::card>
                    @endif
                </div>

                <div class="grid gap-4">
                    @include('catalog::admin.imports._source', ['driver' => $driver, 'endpoint' => $endpoint, 'lastSync' => $lastSync, 'run' => $run, 'progress' => $progress, 'mapped' => $mapped, 'mappings' => $mappings, 'canManage' => $canManage, 'canIntegrations' => $canIntegrations])

                    @if ($unmapped > 0)
                        <div class="flex items-start gap-3 rounded-card border border-warning/30 bg-warning-bg p-4" role="status">
                            <x-ui::icon name="alert-triangle" :size="20" class="mt-0.5 shrink-0 text-warning" />
                            <p class="min-w-0 flex-1 text-[13px] text-ink">{{ trans_choice('catalog::imports.unmapped.message', $unmapped, ['count' => number_format($unmapped)]) }}</p>
                            @if ($canManage)
                                <x-ui::button variant="secondary" size="sm" :href="route('admin.catalog.imports.categories', ['driver' => $driver->value, 'unmapped' => 1])">{{ __('catalog::imports.unmapped.action') }}</x-ui::button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <x-ui::datatable
                id="import-runs"
                :url="route('admin.catalog.imports.runs')"
                :columns="[
                    ['data' => 'created_at', 'title' => __('catalog::imports.history.columns.run'), 'searchable' => false],
                    ['data' => 'status', 'title' => __('catalog::imports.history.columns.status'), 'searchable' => false],
                    ['data' => 'created_count', 'title' => __('catalog::imports.history.columns.created'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'updated_count', 'title' => __('catalog::imports.history.columns.updated'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'skipped_count', 'title' => __('catalog::imports.history.columns.skipped'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'error_count', 'title' => __('catalog::imports.history.columns.errors'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[0, 'desc']]"
                :page-length="5"
                :per-page="[5, 10, 25]"
                :empty-title="__('catalog::imports.history.empty_title')"
                :empty-description="__('catalog::imports.history.empty_description')"
            >
                <x-slot:filters>
                    <div class="grid gap-0.5">
                        <h2 class="text-base font-semibold text-ink">{{ __('catalog::imports.history.title') }}</h2>
                        <p class="text-[13px] text-ink-secondary">{{ __('catalog::imports.history.subtitle') }}</p>
                    </div>
                </x-slot:filters>
            </x-ui::datatable>
    </div>

    @if ($canManage && $configured)
        @include('catalog::admin.imports._run-modal', ['driver' => $driver, 'drivers' => $drivers])
    @endif
</x-layouts.panel>
