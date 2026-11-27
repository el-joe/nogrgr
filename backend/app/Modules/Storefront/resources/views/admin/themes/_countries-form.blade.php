{{--
    Theme countries (ADM-086 Countries modal; old TemplateControlPage toggle each / all):
    a searchable checkbox list of every country with Select all / Clear all. None checked = all countries.
--}}
<form
    id="theme-countries-form"
    method="POST"
    action="{{ route('admin.storefront.themes.countries.update', $theme) }}"
    data-ajax
    novalidate
    class="grid gap-4"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::admin_themes.save') }}"
    data-theme-countries
>
    @csrf
    @method('PUT')
    <p class="text-sm text-ink-secondary">{{ __('content::admin_themes.countries_help') }}</p>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui::input name="country_search" :label="__('content::admin_themes.search_countries')" label-hidden :placeholder="__('content::admin_themes.search_countries')" class="min-w-0 flex-1" data-country-search autocomplete="off" />
        <x-ui::button variant="secondary" size="sm" data-country-all>{{ __('content::admin_themes.select_all') }}</x-ui::button>
        <x-ui::button variant="ghost" size="sm" data-country-none>{{ __('content::admin_themes.clear_all') }}</x-ui::button>
    </div>
    <ul class="grid max-h-80 gap-1 overflow-y-auto rounded-control border border-line p-2 sm:grid-cols-2" role="list" data-country-list>
        @foreach ($countries as $id => $name)
            <li data-country-item data-name="{{ mb_strtolower($name) }}">
                <x-ui::checkbox name="country_ids[]" :id="'theme-country-'.$id" :value="(string) $id" :label="$name" :checked="in_array((int) $id, $selected, true)" />
            </li>
        @endforeach
    </ul>
    <p class="hidden text-sm text-ink-muted" data-country-empty>{{ __('content::admin_themes.no_countries_match') }}</p>
</form>
