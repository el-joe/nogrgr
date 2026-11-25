{{--
    Vendor setup wizard (VEN-007, Vendor Panel (new screens) 82:99932): centered card with
    the 4-step stepper — countries (chips + default country), languages (ar / en on,
    paid languages bought through the hosted checkout), theme (P7 themes page, else the
    "available after" note) and launch. Same frame as the vendor auth pages.
--}}
@use('App\Modules\Platform\Enums\SetupWizardStep')
@php
    $featuredIso = ['SA', 'AE', 'KW', 'BH', 'QA', 'OM', 'EG', 'JO'];
    $featured = $countries->filter(fn ($country) => in_array($country->iso2, $featuredIso, true) || in_array($country->id, $selected, true))
        ->sortBy(fn ($country) => array_search($country->iso2, $featuredIso, true) === false ? 100 : array_search($country->iso2, $featuredIso, true));
    $others = $countries->reject(fn ($country) => $featured->contains('id', $country->id))->mapWithKeys(fn ($country) => [$country->id => trim($country->flag.' '.$country->name)])->all();
    $allOptions = $countries->mapWithKeys(fn ($country) => [$country->id => trim($country->flag.' '.$country->name)])->all();
    $next = $step->next();
    $stepUrl = fn (SetupWizardStep $target) => vendor_route('onboarding.setup', ['step' => $target->value]);
    $locale = app()->getLocale();
@endphp
<x-layouts.auth surface="vendor" :title="__('platform::setup.title')">
    <main class="relative flex min-h-screen flex-col px-4 py-6 sm:px-8" data-page="vendor-setup-wizard">
        <div class="flex justify-end">
            <a href="{{ vendor_route('onboarding', ['tab' => 'checklist']) }}" class="inline-flex h-9 items-center gap-2 rounded-control border border-line bg-surface px-3 text-[13px] font-medium text-ink hover:bg-subtle">
                <x-ui::icon name="chevron-left" :size="16" class="rtl:rotate-180" />{{ __('platform::setup.back_to_checklist') }}
            </a>
        </div>

        <div class="flex flex-1 flex-col items-center justify-center gap-6 py-10">
            <x-ui::logo variant="light" :tag="__('ui.tags.vendor')" data-reveal="fade-up" />
            <section class="w-full max-w-[486px] rounded-card border border-line bg-surface p-6 shadow-raised sm:p-7" aria-labelledby="wizard-title" data-reveal="fade-up">
                <span class="mb-4 grid size-9 place-items-center rounded-control bg-primary-subtle text-ink-brand">
                    <x-ui::icon :name="$step->icon()" :size="18" />
                </span>
                <h1 id="wizard-title" class="text-xl font-semibold leading-7 text-ink">{{ __('platform::setup.'.$step->value.'.title') }}</h1>
                <p class="mt-3 text-[13px] text-ink-secondary">{{ __('platform::setup.'.$step->value.'.subtitle') }}</p>

                <ol class="mt-4 flex items-center gap-2" aria-label="{{ __('platform::setup.title') }}">
                    @foreach (SetupWizardStep::cases() as $item)
                        @php($current = $item === $step)
                        @php($done = $progress[$item->value] && ! $current)
                        <li class="flex min-w-0 flex-1 items-center gap-2 last:flex-none" @if ($current) aria-current="step" @endif>
                            <a href="{{ $stepUrl($item) }}" class="flex shrink-0 items-center gap-1.5 rounded-control focus-visible:outline-2">
                                <span @class([
                                    'grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-semibold',
                                    'bg-primary text-on-primary' => $current,
                                    'bg-success text-on-primary' => $done,
                                    'border border-line-strong text-ink-secondary' => ! $current && ! $done,
                                ])>
                                    @if ($done)
                                        <x-ui::icon name="check" :size="12" />
                                    @else
                                        {{ $item->number() }}
                                    @endif
                                </span>
                                <span @class(['hidden text-[11px] sm:inline', 'font-medium text-ink' => $current, 'text-ink-secondary' => ! $current])>{{ __('platform::setup.steps.'.$item->value) }}</span>
                            </a>
                            @unless ($loop->last)
                                <span @class(['h-px flex-1', 'bg-success' => $done, 'bg-line' => ! $done]) aria-hidden="true"></span>
                            @endunless
                        </li>
                    @endforeach
                </ol>

                <div class="mt-5">
                    @if ($step === SetupWizardStep::Countries)
                        @if ($pendingChange)
                            <p class="mb-4 flex items-start gap-2 rounded-control bg-warning-bg p-3 text-xs text-ink" role="status">
                                <x-ui::icon name="clock" :size="16" class="mt-0.5 shrink-0 text-warning" />{{ __('platform::setup.countries.pending', ['date' => $pendingChange->created_at?->locale($locale)->translatedFormat('j M')]) }}
                            </p>
                        @elseif ($selected !== [])
                            <p class="mb-4 flex items-start gap-2 rounded-control bg-info-bg p-3 text-xs text-ink">
                                <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0 text-info" />{{ __('platform::setup.countries.approval_note') }}
                            </p>
                        @endif
                        <form method="POST" action="{{ vendor_route('onboarding.setup.countries') }}" class="grid gap-4" novalidate>
                            @csrf
                            <fieldset class="grid gap-2" data-field>
                                <legend class="sr-only">{{ __('platform::setup.countries.label') }}</legend>
                                <div class="flex flex-wrap gap-2" data-country-chips>
                                    @foreach ($featured as $country)
                                        <label class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-full border border-line bg-surface px-3 text-xs font-medium text-ink transition-colors hover:bg-subtle has-[:checked]:border-transparent has-[:checked]:bg-inverse has-[:checked]:text-on-inverse has-[:focus-visible]:outline-2">
                                            <input type="checkbox" name="country_ids[]" value="{{ $country->id }}" class="peer sr-only" @checked(in_array($country->id, old('country_ids', $selected), false)) data-country-chip data-name="{{ $country->name }}">
                                            {{ $country->name }}
                                            <x-ui::icon name="check" :size="12" class="hidden peer-checked:inline" />
                                        </label>
                                    @endforeach
                                </div>
                                @error('country_ids')<p class="text-xs text-danger" role="alert">{{ $message }}</p>@enderror
                            </fieldset>
                            @if ($others !== [])
                                <x-ui::select name="country_ids[]" multiple :label="__('platform::setup.countries.more')" :options="$others" :selected="array_values(array_intersect(array_map('intval', (array) old('country_ids', $selected)), array_keys($others)))" :placeholder="__('platform::setup.countries.more_placeholder')" data-country-more />
                            @endif
                            <x-ui::select name="default_country_id" :label="__('platform::setup.countries.default')" :options="$allOptions" :selected="old('default_country_id', $tenant->default_country_id)" :placeholder="__('platform::setup.countries.default_placeholder')" required data-default-country />
                            <x-ui::button type="submit" size="lg" block>{{ __('platform::setup.next', ['step' => mb_strtolower(__('platform::setup.steps.languages'))]) }}</x-ui::button>
                        </form>
                    @elseif ($step === SetupWizardStep::Languages)
                        <ul class="grid gap-2" role="list">
                            @foreach ($storeLanguages as $code)
                                <li class="flex items-center justify-between gap-3 rounded-control border border-line px-3 py-2.5">
                                    <span class="text-sm font-medium text-ink">{{ __('ui.languages.'.$code) !== 'ui.languages.'.$code ? __('ui.languages.'.$code) : strtoupper($code) }}</span>
                                    <x-ui::badge tone="success" dot>{{ in_array($code, ['ar', 'en'], true) ? __('platform::setup.languages.free') : __('platform::setup.languages.on') }}</x-ui::badge>
                                </li>
                            @endforeach
                            @foreach ($purchasable as $row)
                                @php($language = $row['language'])
                                <li class="grid gap-2 rounded-control border border-line px-3 py-2.5" data-language="{{ $language->code }}">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-medium text-ink">{{ $language->name }} <span class="text-xs text-ink-secondary">{{ $language->native_name }}</span></span>
                                        @if ($row['purchase'])
                                            <x-ui::badge tone="warning" dot>{{ __('platform::setup.languages.awaiting_payment') }}</x-ui::badge>
                                        @else
                                            <span class="text-sm font-semibold text-ink" dir="ltr">{{ $language->price()->format() }}</span>
                                        @endif
                                    </div>
                                    @if ($canBuyLanguages && $gateways->isNotEmpty())
                                        <form method="POST" action="{{ vendor_route('onboarding.setup.languages.purchase', ['language' => $language->id]) }}" class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            @if ($gateways->count() === 1)
                                                <input type="hidden" name="gateway" value="{{ $gateways->first()->code->value }}">
                                            @else
                                                <label class="sr-only" for="gateway-{{ $language->id }}">{{ __('platform::setup.languages.gateway') }}</label>
                                                <select id="gateway-{{ $language->id }}" name="gateway" class="ui-control h-9 w-auto text-sm">
                                                    @foreach ($gateways as $gateway)
                                                        <option value="{{ $gateway->code->value }}">{{ $gateway->code->label() }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            <x-ui::button type="submit" size="sm" icon="credit-card">{{ __($row['purchase'] ? 'platform::setup.languages.pay' : 'platform::setup.languages.buy', ['price' => $language->price()->format()]) }}</x-ui::button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($purchasable->isEmpty())
                            <p class="mt-3 text-xs text-ink-secondary">{{ __('platform::setup.languages.none') }}</p>
                        @elseif (! $canBuyLanguages)
                            <p class="mt-3 text-xs text-ink-secondary">{{ __('platform::setup.languages.no_permission') }}</p>
                        @elseif ($gateways->isEmpty())
                            <p class="mt-3 text-xs text-ink-secondary">{{ __('platform::setup.languages.no_gateways') }}</p>
                        @endif
                        <x-ui::button size="lg" block class="mt-5" :href="$stepUrl(SetupWizardStep::Theme)">{{ __('platform::setup.next', ['step' => mb_strtolower(__('platform::setup.steps.theme'))]) }}</x-ui::button>
                    @elseif ($step === SetupWizardStep::Theme)
                        <div class="rounded-control border border-line p-4 text-sm">
                            @if ($theme)
                                <p class="flex items-center gap-2 font-medium text-ink"><x-ui::icon name="check-circle" :size="18" class="text-success" />{{ __('platform::setup.theme.current', ['theme' => $theme]) }}</p>
                            @else
                                <p class="text-ink-secondary">{{ __('platform::setup.theme.none') }}</p>
                            @endif
                            @if ($themesUrl)
                                <x-ui::button variant="secondary" size="sm" icon="palette" class="mt-3" :href="$themesUrl">{{ __('platform::setup.theme.choose') }}</x-ui::button>
                            @else
                                <p class="mt-2 text-xs text-ink-secondary">{{ __('platform::onboarding.steps.theme.available_after') }}</p>
                            @endif
                        </div>
                        <x-ui::button size="lg" block class="mt-5" :href="$stepUrl(SetupWizardStep::Launch)">{{ __('platform::setup.next', ['step' => mb_strtolower(__('platform::setup.steps.launch'))]) }}</x-ui::button>
                    @else
                        <ul class="grid gap-2" role="list">
                            @foreach (SetupWizardStep::cases() as $item)
                                @continue($item === SetupWizardStep::Launch)
                                <li class="flex items-center justify-between gap-3 rounded-control border border-line px-3 py-2.5">
                                    <a href="{{ $stepUrl($item) }}" class="text-sm font-medium text-ink hover:underline">{{ __('platform::setup.steps.'.$item->value) }}</a>
                                    <x-ui::badge :tone="$progress[$item->value] ? 'success' : 'neutral'" dot>{{ $progress[$item->value] ? __('platform::setup.launch.step_done') : __('platform::setup.launch.step_open') }}</x-ui::badge>
                                </li>
                            @endforeach
                        </ul>
                        @if ($launchedAt)
                            <p class="mt-4 flex items-center gap-2 rounded-control bg-success-bg p-3 text-sm text-ink" role="status">
                                <x-ui::icon name="check-circle" :size="18" class="text-success" />{{ __('platform::setup.launch.launched', ['date' => \Illuminate\Support\Carbon::parse($launchedAt)->locale($locale)->translatedFormat('j M Y')]) }}
                            </p>
                            <x-ui::button size="lg" block class="mt-5" :href="vendor_route('dashboard')">{{ __('platform::setup.launch.dashboard') }}</x-ui::button>
                        @else
                            <form method="POST" action="{{ vendor_route('onboarding.setup.launch') }}" class="mt-5">
                                @csrf
                                <x-ui::button type="submit" size="lg" block icon="sparkles" :disabled="! $progress['countries']">{{ __('platform::setup.launch.button') }}</x-ui::button>
                            </form>
                        @endif
                    @endif
                </div>
            </section>
        </div>
    </main>
</x-layouts.auth>
