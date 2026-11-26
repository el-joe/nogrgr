{{--
    Admin › Localization › Translations (ADM-092, Figma 79:82911) and Translation key locks
    (ADM-093, the "Locked" tab + Lock column). File + language pickers, tabs All / Missing
    AR / Missing <lang> / Locked, server-side table (key, English, language value, lock,
    view / edit / revert), JSON export, add key and create file. Saves are central
    overrides (deploy-safe). ADM-092a: "Translate with AI" (#ai-keys-modal, scope this
    file / all missing keys) starts an admin AI run with a live progress modal
    (retry / stop); the edit modal suggests one key (Ai module).
--}}
@php
    /** @var \App\Modules\Localization\Support\TranslationFile $file */
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('localization.translations.manage');
    $languageName = $locales[$locale] ?? strtoupper($locale);
    $query = fn (array $extra = []) => array_merge(['file' => $file->id, 'locale' => $locale], $extra);
    $tabRoute = fn (string $tab) => $tab === 'locked'
        ? route('admin.localization.key-locks.index', $query())
        : route('admin.localization.translations.index', $query($tab === 'all' ? [] : ['tab' => $tab]));
    $tabs = ['all' => ['label' => __('localization::translations.tabs.all'), 'count' => $counts['all'], 'href' => $tabRoute('all')]];
    if ($locale !== 'ar') {
        $tabs['missing_ar'] = ['label' => __('localization::translations.tabs.missing', ['code' => 'AR']), 'count' => $counts['missing_ar'], 'href' => $tabRoute('missing_ar')];
    }
    $tabs['missing'] = ['label' => __('localization::translations.tabs.missing', ['code' => strtoupper($locale)]), 'count' => $counts['missing'], 'href' => $tabRoute('missing')];
    $tabs['locked'] = ['label' => __('localization::translations.tabs.locked'), 'count' => $counts['locked'], 'href' => $tabRoute('locked')];
    $title = $tab === 'locked' ? __('localization::translations.locks_title') : __('localization::translations.title');
@endphp
<x-layouts.panel surface="admin" :title="$title">
    <div data-page="admin-translations" class="grid gap-6">
        <x-ui::page-header
            :title="__('localization::translations.title')"
            :subtitle="__('localization::translations.subtitle')"
            :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.localization') => null, $title => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="download" :href="route('admin.localization.translations.export', $query())">{{ __('localization::translations.export') }}</x-ui::button>
                @if ($canManage && $locale !== 'en')
                    <x-ui::button variant="secondary" icon="sparkles" data-remote-modal="ai-keys-modal" :data-url="route('admin.localization.ai-translation.static-keys.create', ['file' => $file->id, 'locale' => $locale])">{{ __('ai::admin_translation.actions.translate_keys') }}</x-ui::button>
                @endif
                @if ($canManage)
                    <x-ui::button variant="secondary" icon="file" data-remote-modal="translation-modal" :data-url="route('admin.localization.translations.files.create')">{{ __('localization::translations.new_file') }}</x-ui::button>
                    <x-ui::button icon="plus" data-remote-modal="translation-modal" :data-url="route('admin.localization.translations.create', ['file' => $file->id])">{{ __('localization::translations.add_key') }}</x-ui::button>
                @endif
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="translation-tabs" :active="$tab" :label="__('localization::translations.title')" :items="$tabs" />
            </x-slot:tabs>
        </x-ui::page-header>

        <form method="GET" action="{{ $tab === 'locked' ? route('admin.localization.key-locks.index') : route('admin.localization.translations.index') }}" data-translations-scope class="flex flex-wrap items-end gap-3">
            @if (! in_array($tab, ['all', 'locked'], true))
                <input type="hidden" name="tab" value="{{ $tab }}">
            @endif
            <x-ui::select name="file" :label="__('localization::translations.file')" :options="$files" :selected="$file->id" class="w-full sm:w-80" />
            <x-ui::select name="locale" :label="__('localization::translations.language')" :options="$locales" :selected="$locale" class="w-full sm:w-56" />
            <x-ui::button type="submit" variant="secondary" data-scope-submit>{{ __('localization::translations.apply') }}</x-ui::button>
        </form>

        @include('ai::admin.translation.banner', ['kind' => \App\Modules\Ai\Enums\AdminTranslationKind::StaticKeys])

        @if ($file->isStorefront())
            <div class="flex items-start gap-2 rounded-control bg-info-bg p-3 text-sm text-ink" role="note">
                <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
                <p>{{ __('localization::translations.hints.storefront') }}</p>
            </div>
        @endif

        <x-ui::datatable
            id="translations"
            :url="route('admin.localization.translations.data', $query(['tab' => $tab]))"
            :columns="[
                ['data' => 'key', 'title' => __('localization::translations.columns.key')],
                ['data' => 'source', 'title' => __('localization::translations.columns.english')],
                ['data' => 'value', 'title' => $languageName],
                ['data' => 'lock', 'title' => __('localization::translations.columns.lock'), 'orderable' => false, 'searchable' => false],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[0, 'asc']]"
            :empty-title="__('localization::translations.empty_title')"
            :empty-description="__('localization::translations.empty_description')"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="translations" :search-placeholder="__('localization::translations.search')" />
            </x-slot:filters>
        </x-ui::datatable>

        @if ($counts['missing'] > 0 && ! in_array($locale, ['en', 'ar'], true))
            <div class="flex items-start gap-2 rounded-control bg-warning-bg p-3 text-sm text-ink" role="status">
                <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
                <p>{{ __('localization::translations.missing_banner', ['count' => $counts['missing'], 'language' => $languageName]) }}</p>
            </div>
        @endif
    </div>

    <x-ui::modal id="translation-modal" :title="__('localization::translations.title')" size="lg">
        @include('identity::admin._remote-modal-states', ['error' => __('localization::translations.load_error')])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="translation-form" icon="check" data-remote-submit>{{ __('localization::translations.save') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>

    @if ($canManage && $locale !== 'en')
        <x-ui::modal id="ai-keys-modal" :title="__('ai::admin_translation.keys.title')" size="sm">
            @include('identity::admin._remote-modal-states', ['error' => __('localization::translations.load_error')])
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="ai-keys-form" icon="sparkles" data-remote-submit>{{ __('ai::admin_translation.actions.translate_keys') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif

    @include('ai::admin.translation.progress-modal')

    <x-ui::modal id="translation-view-modal" :title="__('localization::translations.title')" size="lg">
        @include('identity::admin._remote-modal-states', ['error' => __('localization::translations.load_error')])
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.close') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-layouts.panel>
