{{--
    Vendor onboarding (VEN-007 / 007a / 007b), Vendor Panel (new screens) 82:100227:
    "Welcome, {name}", tabs Tour | Setup checklist, the 8-step checklist with status
    chips, the setup progress widget (VEN-008) and the product tour card.
--}}
@php
    /** @var \App\Modules\Platform\DTOs\SetupProgressData $progress */
    $firstName = \Illuminate\Support\Str::before(trim((string) $staff->name), ' ') ?: $staff->name;
    $tabs = [
        'tour' => ['label' => __('platform::onboarding.tabs.tour'), 'href' => vendor_route('onboarding', ['tab' => 'tour'])],
        'checklist' => ['label' => __('platform::onboarding.tabs.checklist'), 'href' => vendor_route('onboarding', ['tab' => 'checklist'])],
    ];
    $chip = [
        'done' => 'bg-success-bg text-success',
        'skipped' => 'bg-warning-bg text-warning',
        'next' => 'bg-primary-subtle text-ink-brand',
        'pending' => 'bg-subtle text-ink-secondary',
        'optional' => 'bg-subtle text-ink-secondary',
    ];
    $totalStops = count($tour);
@endphp
<x-vendor::layout :title="__('platform::onboarding.title')" page="vendor-onboarding">
    <x-ui::page-header
        :title="__('platform::onboarding.welcome', ['name' => $firstName])"
        :subtitle="__('platform::onboarding.subtitle')"
        :breadcrumbs="[__('platform::panel.dashboard') => vendor_route('dashboard'), __('platform::onboarding.title') => null]"
        data-reveal="fade-up"
    >
        @if ($canManage)
            <x-slot:actions>
                <x-ui::button icon="sparkles" :href="vendor_route('onboarding.setup')">{{ __('platform::setup.open') }}</x-ui::button>
            </x-slot:actions>
        @endif
        <x-slot:tabs>
            <x-ui::tabs id="onboarding-tabs" :items="$tabs" :active="$tab" :label="__('platform::onboarding.title')" />
        </x-slot:tabs>
    </x-ui::page-header>

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
        @if ($tab === 'checklist')
            <section class="rounded-card border border-line bg-surface p-5 shadow-card" aria-labelledby="checklist-title" data-reveal="fade-up">
                <header class="flex items-center justify-between gap-3">
                    <h2 id="checklist-title" class="text-base font-semibold text-ink">{{ __('platform::onboarding.tabs.checklist') }}</h2>
                    <span class="inline-flex h-6 items-center gap-1.5 rounded-full bg-primary-subtle px-2.5 text-xs font-medium text-ink-brand" dir="ltr">
                        <span class="size-1.5 rounded-full bg-primary" aria-hidden="true"></span>{{ __('platform::onboarding.of', ['done' => $progress->completed, 'total' => $progress->total]) }}
                    </span>
                </header>

                <ol class="mt-4 grid" data-reveal-stagger>
                    @foreach ($progress->steps as $item)
                        <li class="flex items-center gap-4 border-b border-line py-3 last:border-b-0" data-step="{{ $item->step->value }}">
                            <span @class(['grid size-9 shrink-0 place-items-center rounded-full', 'bg-success-bg text-success' => $item->state->isComplete(), 'bg-subtle text-ink-secondary' => ! $item->state->isComplete()])>
                                <x-ui::icon :name="$item->state->isComplete() ? 'check-circle' : $item->step->icon()" :size="20" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-ink">
                                    @if ($item->url && ! $item->state->isComplete())
                                        <a href="{{ $item->url }}" class="hover:text-ink-brand hover:underline">{{ $item->title }}</a>
                                    @else
                                        {{ $item->title }}
                                    @endif
                                </p>
                                <p class="truncate text-xs text-ink-secondary">{{ $item->detail }}</p>
                                @if ($item->availableAfter && ! $item->state->isComplete())
                                    <p class="mt-1 text-xs text-ink-muted">{{ $item->availableAfter }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                @if ($item->step === \App\Modules\Platform\Enums\SetupStep::Logo && $canUploadLogo)
                                    <x-ui::button size="sm" :variant="$item->state->isComplete() ? 'secondary' : 'primary'" data-overlay-open="logo-modal">{{ $item->state->isComplete() ? __('platform::onboarding.logo.replace') : __('platform::onboarding.logo.upload') }}</x-ui::button>
                                @elseif ($item->step === \App\Modules\Platform\Enums\SetupStep::PaymentReadiness && $canManage && ! $item->state->isComplete())
                                    <form method="POST" action="{{ vendor_route('onboarding.payment-readiness.skip') }}">
                                        @csrf
                                        <button type="submit" class="inline-flex h-6 items-center gap-1.5 rounded-full bg-warning-bg px-2.5 text-xs font-medium text-warning hover:underline">
                                            <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ __('platform::onboarding.payment.skip') }}
                                        </button>
                                    </form>
                                @elseif ($item->step === \App\Modules\Platform\Enums\SetupStep::Tour && ! $item->state->isComplete())
                                    <x-ui::button size="sm" variant="secondary" :href="vendor_route('onboarding', ['tab' => 'tour'])">{{ __('platform::onboarding.tour.start') }}</x-ui::button>
                                @endif
                                @unless ($item->step === \App\Modules\Platform\Enums\SetupStep::PaymentReadiness && $canManage && ! $item->state->isComplete())
                                    <span class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-medium {{ $chip[$item->state->value] }}">
                                        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $item->state->label() }}
                                    </span>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @else
            <section class="rounded-card border border-line bg-surface p-5 shadow-card sm:p-6" aria-labelledby="tour-title" data-tour data-reveal="fade-up">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="tour-title" class="text-base font-semibold text-ink">{{ __('platform::onboarding.tour.title') }}</h2>
                    @if ($progress->tourFinished)
                        <x-ui::badge tone="success" dot>{{ __('platform::onboarding.tour.finished') }}</x-ui::badge>
                    @endif
                </header>
                <p class="mt-1 text-sm text-ink-secondary">{{ __('platform::onboarding.tour.intro') }}</p>

                <ol class="mt-5 grid gap-3" data-tour-steps>
                    @foreach ($tour as $index => $stop)
                        <li class="vnd-tour-stop rounded-control border border-line p-4" data-tour-step="{{ $index }}" @if ($index === 0) data-current @endif>
                            <p class="text-xs font-medium text-ink-brand" dir="auto">{{ __('platform::onboarding.tour.step_of', ['step' => $index + 1, 'total' => $totalStops]) }}</p>
                            <h3 class="mt-1 text-[15px] font-semibold text-ink">{{ __('platform::onboarding.tour.stops.'.$stop['key'].'.title') }}</h3>
                            <p class="mt-1 text-sm leading-6 text-ink-secondary">{{ __('platform::onboarding.tour.stops.'.$stop['key'].'.body') }}</p>
                            @if ($stop['url'])
                                <a href="{{ $stop['url'] }}" class="mt-2 inline-flex items-center gap-1 text-[13px] font-medium text-ink-brand hover:underline">{{ __('platform::onboarding.tour.open') }}<x-ui::icon name="chevron-right" :size="14" data-flip /></a>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($canManage && ! $progress->tourFinished)
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                        <form method="POST" action="{{ vendor_route('onboarding.tour.dismiss') }}">
                            @csrf
                            <x-ui::button type="submit" variant="ghost">{{ __('platform::onboarding.tour.skip') }}</x-ui::button>
                        </form>
                        <form method="POST" action="{{ vendor_route('onboarding.tour.complete') }}">
                            @csrf
                            <x-ui::button type="submit" icon="check">{{ __('platform::onboarding.tour.complete') }}</x-ui::button>
                        </form>
                    </div>
                @endif
            </section>
        @endif

        <aside class="grid gap-6">
            <x-vendor::setup-progress :progress="$progress" />

            <section class="rounded-card border border-line bg-surface p-5 shadow-card" aria-labelledby="product-tour-title" data-tour-card data-reveal="fade-up">
                <h2 id="product-tour-title" class="text-base font-semibold text-ink">{{ __('platform::onboarding.tour.card_title') }}</h2>
                @foreach ($tour as $index => $stop)
                    <p class="mt-4 text-sm leading-6 text-ink-secondary" data-tour-card-step="{{ $index }}" @if ($index !== 0) hidden @endif>
                        {{ __('platform::onboarding.tour.card_step', ['step' => $index + 1, 'total' => $totalStops, 'title' => __('platform::onboarding.tour.stops.'.$stop['key'].'.title'), 'body' => __('platform::onboarding.tour.stops.'.$stop['key'].'.short')]) }}
                    </p>
                @endforeach
                <div class="mt-4 flex items-center gap-3">
                    <x-ui::button variant="secondary" data-tour-prev disabled>{{ __('platform::onboarding.tour.back') }}</x-ui::button>
                    <x-ui::button data-tour-next>{{ __('platform::onboarding.tour.next') }}</x-ui::button>
                </div>
            </section>
        </aside>
    </div>

    @if ($canUploadLogo)
        <x-ui::modal id="logo-modal" :title="__('platform::onboarding.logo.title')" :subtitle="__('platform::onboarding.logo.hint')" size="sm" close-on-success>
            <form method="POST" action="{{ vendor_route('onboarding.logo') }}" enctype="multipart/form-data" class="grid gap-4" data-ajax data-logo-form novalidate>
                @csrf
                <div class="flex items-center gap-4">
                    <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-full bg-subtle text-ink-muted" data-logo-preview>
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ __('platform::onboarding.logo.current') }}" class="size-full object-cover" width="64" height="64">
                        @else
                            <x-ui::icon name="image" :size="24" />
                        @endif
                    </span>
                    <label class="grid min-w-0 flex-1 gap-1.5">
                        <span class="text-[13px] font-medium text-ink">{{ __('platform::onboarding.logo.field') }}</span>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required class="block w-full text-sm text-ink-secondary file:me-3 file:rounded-control file:border-0 file:bg-primary-subtle file:px-3 file:py-2 file:text-[13px] file:font-medium file:text-ink-brand" data-logo-input>
                        <span class="text-xs text-ink-muted">{{ __('platform::onboarding.logo.rules') }}</span>
                        <span class="text-xs text-danger" data-error-for="logo" hidden></span>
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" :loading-text="__('ui.saving')">{{ __('platform::onboarding.logo.save') }}</x-ui::button>
                </div>
            </form>
        </x-ui::modal>
    @endif
</x-vendor::layout>
