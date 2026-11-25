{{--
    Select store (OWN-002, Figma Affiliate & Owner 122:105321): after a sign-in that
    matched several stores — split owner layout, radio list of the owner's stores
    (suspended / not launched ones disabled), "Open store panel" and "Go to My stores".
    Inputs: $cards (OwnerPortalService::cards), $openable (tenant ids the picker allows).
--}}
@php
    $selectable = collect($cards)->filter(fn (array $card) => in_array($card['tenant']->id, $openable, true) && $card['state']->canOpenPanel());
    $firstId = old('tenant_id', $selectable->first()['tenant']->id ?? null);
    $locales = ['en' => 'English', 'ar' => 'العربية'];
@endphp
<x-layouts.auth surface="owner" :title="__('platform::owner.select.title')">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,7fr)_minmax(0,11fr)]" data-page="owner-select-store">
        <aside class="hidden flex-col justify-between bg-sidebar p-10 text-white lg:flex">
            <x-ui::logo variant="dark" :tag="__('ui.tags.owner')" />
            <div class="grid gap-6">
                <h2 class="text-3xl font-semibold">{{ __('platform::owner.select.headline') }}</h2>
                <p class="text-sm text-white/80">{{ __('platform::owner.select.intro') }}</p>
                <ul class="grid gap-4 text-sm">
                    @foreach (['store' => 'switch', 'globe' => 'domains', 'file' => 'invoices'] as $icon => $key)
                        <li class="flex items-center gap-3">
                            <span class="grid size-8 place-items-center rounded-control bg-white text-primary"><x-ui::icon :name="$icon" :size="16" /></span>
                            {{ __('platform::owner.select.points.'.$key) }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="text-xs text-white/70">© {{ now()->year }} {{ config('app.name') }}</p>
        </aside>

        <main class="grid place-items-center bg-canvas px-4 py-10">
            <div class="grid w-full max-w-md gap-4">
                <section class="grid gap-5 rounded-card border border-line bg-surface p-6 shadow-card">
                    <header class="grid gap-2">
                        <h1 class="text-2xl font-semibold text-ink">{{ __('platform::owner.select.title') }}</h1>
                        <p class="text-sm text-ink-secondary">{{ trans_choice('platform::owner.select.subtitle', count($cards), ['count' => count($cards)]) }}</p>
                    </header>

                    <form method="POST" action="{{ route('owner.select-store.store') }}" class="grid gap-5">
                        @csrf
                        <fieldset class="grid gap-3">
                            <legend class="mb-2 text-sm font-medium text-ink">{{ __('platform::owner.select.stores') }}</legend>
                            @foreach ($cards as $card)
                                @php
                                    $tenant = $card['tenant'];
                                    $enabled = in_array($tenant->id, $openable, true) && $card['state']->canOpenPanel();
                                    $suffix = $enabled ? '' : ' ('.$card['state']->label().')';
                                @endphp
                                <x-ui::radio name="tenant_id" :value="$tenant->id" :checked="(string) $firstId === (string) $tenant->id" :disabled="! $enabled"
                                    :label="$tenant->name.' · '.$tenant->subdomainHost().$suffix" data-store-option="{{ $tenant->slug }}" />
                            @endforeach
                            @error('tenant_id')
                                <p class="text-xs text-danger" role="alert">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        @if ($selectable->isEmpty())
                            <p class="rounded-control bg-warning-bg px-4 py-3 text-sm text-ink">{{ __('platform::owner.select.none_open') }}</p>
                        @endif

                        <x-ui::button type="submit" block :disabled="$selectable->isEmpty()">{{ __('platform::owner.select.open') }}</x-ui::button>
                        <a href="{{ route('owner.dashboard') }}" class="text-center text-sm font-medium text-ink-brand hover:underline">{{ __('platform::owner.select.my_stores') }}</a>
                    </form>
                </section>
                <nav class="flex justify-center gap-2 text-xs text-ink-secondary" aria-label="{{ __('ui.language') }}">
                    @foreach ($locales as $code => $name)
                        <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" @class(['hover:text-ink', 'font-medium text-ink' => app()->getLocale() === $code])>{{ $name }}</a>
                        @unless ($loop->last)<span aria-hidden="true">·</span>@endunless
                    @endforeach
                </nav>
            </div>
        </main>
    </div>
</x-layouts.auth>
