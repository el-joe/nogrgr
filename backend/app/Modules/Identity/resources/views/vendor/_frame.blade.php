{{--
    Vendor auth frame (Vendor Panel new screens 82:99816 / 82:99866 / 82:99909):
    canvas, centered logo + VENDOR tag, white card. Child views fill @section('card').
    Inputs: $title.
--}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
@endphp
<x-layouts.auth surface="vendor" :title="$title">
    <main class="relative flex min-h-screen flex-col px-4 py-6 sm:px-8">
        @if (count($locales) > 1)
            <div class="flex justify-end">
                <x-ui::dropdown align="end" :width="180" :label="__('ui.language')">
                    <x-slot:trigger>
                        <button type="button" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" class="flex h-9 items-center gap-2 rounded-control border border-line bg-surface px-3 text-[13px] font-medium text-ink hover:bg-subtle">
                            <x-ui::icon name="globe" :size="16" class="text-ink-secondary" />
                            {{ $locales[app()->getLocale()]['native'] ?? app()->getLocale() }}
                            <x-ui::icon name="chevron-down" :size="14" class="text-ink-muted" />
                        </button>
                    </x-slot:trigger>
                    @foreach ($locales as $code => $language)
                        <x-ui::dropdown-item :href="request()->fullUrlWithQuery(['lang' => $code])" :lang="$code">{{ $language['native'] }}</x-ui::dropdown-item>
                    @endforeach
                </x-ui::dropdown>
            </div>
        @endif

        <div class="flex flex-1 flex-col items-center justify-center gap-6 py-10">
            <x-ui::logo variant="light" :tag="__('ui.tags.vendor')" data-reveal="fade-up" />
            <section class="w-full max-w-[440px] rounded-card border border-line bg-surface p-6 shadow-raised sm:p-9" aria-labelledby="auth-title" data-reveal="fade-up">
                @yield('card')
            </section>
        </div>
    </main>
</x-layouts.auth>
