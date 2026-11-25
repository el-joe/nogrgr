{{--
    Admin › Settings › Email templates (ADM-102, Figma Admin Panel 79:89188): audience
    tabs with counts, server-side table (search, status, language filters), preview,
    edit, send test, sync to tenants (one / all), delete.
--}}
@php
    use App\Modules\Notifications\Enums\EmailAudience;

    $canManage = (bool) auth('admin')->user()?->can('email-templates.manage');
    $tabs = ['all' => ['label' => __('notifications::templates.tabs.all'), 'count' => $counts['all'], 'href' => route('admin.settings.email-templates.index')]];

    foreach (EmailAudience::cases() as $audience) {
        $tabs[$audience->value] = ['label' => $audience->label(), 'count' => $counts[$audience->value], 'href' => route('admin.settings.email-templates.index', ['tab' => $audience->value])];
    }

    $tableUrl = route('admin.settings.email-templates.data', $tab === 'all' ? [] : ['audience' => $tab]);
@endphp
<x-layouts.panel surface="admin" :title="__('notifications::templates.title')" data-page="admin-email-templates">
    <x-ui::page-header
        :title="__('notifications::templates.title')"
        :subtitle="__('notifications::templates.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('notifications::templates.title') => null]"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button
                    variant="secondary"
                    icon="refresh"
                    :data-confirm="__('notifications::templates.sync_all_message')"
                    :data-confirm-title="__('notifications::templates.sync_all_title')"
                    :data-confirm-label="__('notifications::templates.sync_all')"
                    data-confirm-tone="brand"
                    data-confirm-method="POST"
                    :data-confirm-url="route('admin.settings.email-templates.sync-all')"
                    :disabled="$counts['all'] === 0"
                >{{ __('notifications::templates.sync_all') }}</x-ui::button>
                @if ($canCreate)
                    <x-ui::button icon="plus" :href="route('admin.settings.email-templates.create')">{{ __('notifications::templates.add') }}</x-ui::button>
                @endif
            </x-slot:actions>
        @endif
        <x-slot:tabs>
            <x-ui::tabs id="email-template-tabs" :active="$tab" :label="__('notifications::templates.title')" :items="$tabs" />
        </x-slot:tabs>
    </x-ui::page-header>

    <x-ui::datatable
        id="email-templates"
        :url="$tableUrl"
        :columns="[
            ['data' => 'name', 'title' => __('notifications::templates.columns.template')],
            ['data' => 'audience', 'title' => __('notifications::templates.columns.audience'), 'searchable' => false],
            ['data' => 'languages', 'title' => __('notifications::templates.columns.languages'), 'orderable' => false, 'searchable' => false],
            ['data' => 'updated_at', 'title' => __('notifications::templates.columns.updated'), 'searchable' => false],
            ['data' => 'status', 'title' => __('notifications::templates.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'desc']]"
        :empty-title="__('notifications::templates.empty_title')"
        :empty-description="__('notifications::templates.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="email-templates" :search-placeholder="__('notifications::templates.search')">
                <x-ui::select name="language" :label="__('notifications::templates.filters.language')" label-hidden :options="\App\Modules\Notifications\Enums\EmailTemplateLocaleState::options()" :placeholder="__('notifications::templates.filters.language')" :search="false" allow-clear data-filter :data-filter-label="__('notifications::templates.filters.language')" class="w-44" />
                <x-ui::select name="status" :label="__('notifications::templates.columns.status')" label-hidden :options="\App\Modules\Notifications\Enums\EmailTemplateStatus::options()" :placeholder="__('notifications::templates.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('notifications::templates.columns.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($canManage && $canCreate)
            <x-slot:empty>
                <x-ui::button icon="plus" :href="route('admin.settings.email-templates.create')">{{ __('notifications::templates.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>

    <x-ui::modal id="template-preview-modal" :title="__('notifications::templates.preview')" size="lg">
        <div class="mb-3 flex justify-end">
            <div class="inline-flex gap-1 rounded-control bg-subtle p-1" role="group" aria-label="{{ __('notifications::templates.fields.locale') }}">
                @foreach (['en', 'ar'] as $locale)
                    <button type="button" data-preview-locale="{{ $locale }}" aria-pressed="{{ $locale === 'en' ? 'true' : 'false' }}" class="inline-flex h-8 items-center rounded-chip px-3 text-[13px] text-ink-secondary aria-pressed:bg-surface aria-pressed:font-medium aria-pressed:text-ink aria-pressed:shadow-card">{{ __('notifications::templates.locales.'.$locale) }}</button>
                @endforeach
            </div>
        </div>
        <iframe title="{{ __('notifications::templates.preview') }}" data-template-preview-frame sandbox class="h-[480px] w-full rounded-control border border-line bg-white"></iframe>
    </x-ui::modal>

    @if ($canManage)
        @include('notifications::admin.email-templates._test-modal', ['action' => null])
    @endif
</x-layouts.panel>
