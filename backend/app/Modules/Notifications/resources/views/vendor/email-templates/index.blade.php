{{--
    Vendor › Settings › Email templates (VEN-080, Figma Vendor Panel 91:114835): the e-mails
    the store's shoppers receive, each "Custom" (the store's version) or "Default" (platform
    template) — search + source filter, edit (VEN-080a), preview, reset to default, and
    "Sync from platform" for every template the store did not customise.
--}}
<x-vendor::layout :title="__('notifications::vendor_templates.title')" page="vendor-email-templates">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('notifications::vendor_templates.title')" :subtitle="__('notifications::vendor_templates.subtitle')" :breadcrumbs="[__('notifications::vendor_templates.crumb') => null, __('notifications::vendor_templates.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="refresh" :data-confirm="__('notifications::vendor_templates.sync_message')" :data-confirm-title="__('notifications::vendor_templates.sync_title')" data-confirm-method="POST" data-confirm-tone="primary" :data-confirm-label="__('notifications::vendor_templates.sync')" :data-confirm-url="vendor_route('settings.email-templates.sync')">{{ __('notifications::vendor_templates.sync') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::datatable
            id="vendor-email-templates"
            :url="vendor_route('settings.email-templates.data')"
            :columns="[
                ['data' => 'template', 'title' => __('notifications::vendor_templates.columns.template'), 'orderable' => false],
                ['data' => 'source', 'title' => __('notifications::vendor_templates.columns.source'), 'orderable' => false],
                ['data' => 'languages', 'title' => __('notifications::vendor_templates.columns.languages'), 'orderable' => false],
                ['data' => 'updated', 'title' => __('notifications::vendor_templates.columns.updated'), 'orderable' => false],
                ['data' => 'actions', 'title' => __('notifications::vendor_templates.columns.actions'), 'orderable' => false, 'searchable' => false, 'align' => 'end'],
            ]"
            :page-length="25"
            :per-page="[25]"
            :empty-title="__('notifications::vendor_templates.empty_title')"
            :empty-description="__('notifications::vendor_templates.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-email-templates" :search-placeholder="__('notifications::vendor_templates.search')">
                    <x-ui::select name="source" :label="__('notifications::vendor_templates.filters.source')" label-hidden :options="__('notifications::vendor_templates.sources')" :placeholder="__('notifications::vendor_templates.filters.source')" :search="false" allow-clear data-filter :data-filter-label="__('notifications::vendor_templates.filters.source')" class="w-40" />
                </x-ui::filters-bar>
            </x-slot:filters>
        </x-ui::datatable>
    </div>

    <x-ui::modal id="template-preview-modal" :title="__('notifications::vendor_templates.preview')" size="lg">
        <div class="grid gap-3">
            <div class="inline-flex w-fit gap-1 rounded-control bg-subtle p-1" role="group" aria-label="{{ __('notifications::templates.fields.locale') }}">
                @foreach (\App\Modules\Notifications\Models\EmailTemplate::LOCALES as $locale)
                    <button type="button" data-preview-locale="{{ $locale }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" class="inline-flex h-7 items-center rounded-chip px-2.5 text-xs text-ink-secondary aria-pressed:bg-surface aria-pressed:font-medium aria-pressed:text-ink-brand aria-pressed:shadow-card">{{ __('notifications::templates.locales.'.$locale) }}</button>
                @endforeach
            </div>
            <iframe title="{{ __('notifications::vendor_templates.preview') }}" class="h-[60vh] w-full rounded-card border border-line bg-white" sandbox="" data-template-preview-frame></iframe>
        </div>
    </x-ui::modal>
</x-vendor::layout>
