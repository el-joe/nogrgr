{{--
    Shared frame of the panel auth pages (admin / vendor / affiliate), Figma Login 55:20387:
    brand gradient panel + card with language switch. Child views fill @section('card').
    Inputs: $surface, $title, optional $stats (admin: live platform counts).
--}}
@php
    $stats = $stats ?? [];
    $locales = \App\Shared\Support\Locale::enabled();
    $number = fn ($value) => \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale());
@endphp
<x-layouts.auth :surface="$surface" :title="$title">
    <div class="grid min-h-screen lg:grid-cols-2">
        <aside class="relative hidden overflow-hidden bg-linear-to-br from-[var(--nog-highlight-from)] to-[var(--nog-highlight-to)] p-16 text-white lg:flex lg:flex-col" aria-hidden="false">
            <span class="pointer-events-none absolute -end-24 -top-24 size-[400px] rounded-full border-[48px] border-white/10" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -bottom-40 -start-32 size-[480px] rounded-full border-[48px] border-white/10" aria-hidden="true"></span>
            <span class="pointer-events-none absolute -end-16 bottom-24 size-[240px] rounded-full border-[48px] border-white/10" aria-hidden="true"></span>

            <x-ui::logo variant="hero" :tag="__('ui.tags.'.$surface)" class="relative" />

            <div class="relative mt-auto grid max-w-xl gap-5" data-reveal="fade-up">
                <h2 class="text-5xl font-semibold leading-[1.12] tracking-tight">{{ __('identity::auth.panel.'.$surface.'.headline') }}</h2>
                <p class="text-base leading-6 text-white/85">{{ __('identity::auth.panel.'.$surface.'.subtitle') }}</p>
                @if ($stats)
                    <dl class="mt-6 flex flex-wrap gap-3">
                        @foreach ($stats as $key => $value)
                            <div class="rounded-control bg-white/12 px-4 py-3 backdrop-blur-sm">
                                <dt class="sr-only">{{ __('identity::auth.stats.'.$key) }}</dt>
                                <dd class="text-xl font-semibold" dir="ltr">{{ $number($value) }}</dd>
                                <dd class="text-xs text-white/80">{{ __('identity::auth.stats.'.$key) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            <p class="relative mt-auto text-xs text-white/75">{{ __('identity::auth.copyright', ['year' => now()->year, 'console' => __('identity::auth.panel.'.$surface.'.console')]) }}</p>
        </aside>

        <main class="relative flex min-h-screen flex-col px-4 py-6 sm:px-8">
            <div class="flex items-center justify-between gap-3">
                <x-ui::logo variant="light" :tag="__('ui.tags.'.$surface)" class="lg:invisible" />
                @if (count($locales) > 1)
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
                @endif
            </div>

            <div class="flex flex-1 items-center justify-center py-10">
                <section class="w-full max-w-[440px] rounded-card border border-line bg-surface p-6 shadow-raised sm:p-9" aria-labelledby="auth-title" data-reveal="fade-up">
                    @yield('card')
                </section>
            </div>
        </main>
    </div>
</x-layouts.auth>
