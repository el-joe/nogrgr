{{-- Shared header of the two translation pages: section switch + language picker. --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <nav class="inline-flex rounded-control border border-line bg-surface p-1" aria-label="{{ __('localization::vendor_translations.title') }}">
        @foreach (['static' => vendor_route('settings.translations', ['locale' => $locale]), 'content' => vendor_route('settings.translations.content', ['locale' => $locale])] as $key => $href)
            <a href="{{ $href }}" @class(['rounded-[6px] px-3 py-1.5 text-[13px] font-medium', 'bg-primary-subtle text-ink-brand' => $section === $key, 'text-ink-secondary hover:text-ink' => $section !== $key]) @if ($section === $key) aria-current="page" @endif>{{ __('localization::vendor_translations.sections.'.$key) }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ $section === 'static' ? vendor_route('settings.translations') : vendor_route('settings.translations.content') }}" class="flex items-center gap-2" data-translation-locale>
        @foreach (request()->only(['tab', 'type', 'field']) as $name => $keep)
            <input type="hidden" name="{{ $name }}" value="{{ $keep }}">
        @endforeach
        <x-ui::select name="locale" :label="__('localization::vendor_translations.fields.language')" label-hidden :options="$locales" :selected="$locale" :search="false" class="w-48" data-auto-submit />
        <noscript><x-ui::button type="submit" size="sm" variant="secondary">{{ __('localization::vendor_translations.apply') }}</x-ui::button></noscript>
    </form>
</div>
