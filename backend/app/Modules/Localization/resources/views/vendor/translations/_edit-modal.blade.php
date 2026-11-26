{{-- Edit one text in the chosen language; "Translate" fills in a suggestion (custom AI provider). --}}
@if ($canManage)
    <x-ui::modal id="translation-edit-modal" :title="__('localization::vendor_translations.edit_title', ['language' => $locales[$locale] ?? $locale])" close-on-success>
        <form id="translation-edit-form" method="POST" action="{{ vendor_route('settings.translations.static.update') }}" data-ajax novalidate class="grid gap-4" data-translation-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="locale" value="{{ $locale }}">
            <input type="hidden" name="key" data-edit-field="key" disabled>
            <input type="hidden" name="type" data-edit-field="type" disabled>
            <input type="hidden" name="id" data-edit-field="id" disabled>
            <input type="hidden" name="field" data-edit-field="field" disabled>
            <p class="text-[13px] font-medium text-ink" data-edit-label dir="auto"></p>
            <div class="grid gap-1.5">
                <span class="text-xs font-medium text-ink-secondary">{{ __('localization::vendor_translations.columns.english') }}</span>
                <p class="rounded-control border border-line bg-subtle px-3 py-2 text-sm text-ink" lang="en" dir="ltr" data-edit-english></p>
            </div>
            <x-ui::textarea name="value" :label="$locales[$locale] ?? $locale" :rows="4" data-edit-value />
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-ink-secondary">{{ __('localization::vendor_translations.edit_hint') }}</p>
                <x-ui::button variant="secondary" size="sm" icon="sparkles" data-translation-suggest>{{ __('localization::vendor_translations.translate') }}</x-ui::button>
            </div>
        </form>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="translation-edit-form" icon="check">{{ __('localization::vendor_translations.save') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
