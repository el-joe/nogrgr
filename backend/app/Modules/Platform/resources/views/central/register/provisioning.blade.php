{{--
    WEB-020 Provisioning / loading (Figma Central Website 12:42305, mobile 12:43549):
    brand icon, "Preparing …" title and two checklists (store setup · languages) that
    tick as the store is built; then the success or failure state. Polls $statusUrl.
    Inputs: $tenant, $state (status payload), $statusUrl, $storeHost, $customDomain, $supportEmail.
--}}
@extends('platform::central.register._page', ['title' => __('platform::register.provisioning_title'), 'page' => 'register-provisioning'])

@php
    $phase = $state['done'] ? 'success' : ($state['failed'] ? 'failed' : 'progress');
    $storeSteps = [['database', 0], ['owner', 2], ['plan', 3]];
    $languageSteps = [['english', 1, 'en'], ['arabic', 1, 'ar']];
    $mailto = $supportEmail !== ''
        ? 'mailto:'.$supportEmail.'?subject='.rawurlencode(__('platform::register.provisioning.support_subject', ['store' => $tenant->slug]))
        : null;
    $config = ['statusUrl' => $statusUrl, 'phase' => $phase, 'steps' => 4, 'doneLabel' => __('platform::register.steps.done')];
@endphp

@section('content')
    <script type="application/json" data-provisioning-config>@json($config)</script>
    <div class="flex flex-col items-center px-4 pt-10 pb-20 sm:px-6 lg:pt-[95px] lg:pb-[120px]" data-provisioning data-phase="{{ $phase }}">
        {{-- Icon: bars while working, check on success, cross on failure --}}
        <div class="reg-enter relative grid size-24 place-items-center rounded-[70px] border-[17px] border-(--reg-ring) bg-primary text-white" data-provisioning-icon aria-hidden="true">
            <span class="reg-halo absolute size-24 rounded-full" data-when="progress" @if ($phase !== 'progress') hidden @endif></span>
            <span class="reg-bars flex h-[26px] items-end gap-[3px]" data-when="progress" @if ($phase !== 'progress') hidden @endif>
                <span class="block h-[26px] w-[9px] rounded-[2px] bg-white"></span>
                <span class="block h-[18px] w-[9px] rounded-[2px] bg-white"></span>
                <span class="block h-[12px] w-[9px] rounded-[2px] bg-white"></span>
            </span>
            <span class="reg-pop" data-when="success" @if ($phase !== 'success') hidden @endif><x-ui::icon name="check" :size="32" /></span>
            <span class="reg-pop" data-when="failed" @if ($phase !== 'failed') hidden @endif><x-ui::icon name="x" :size="32" /></span>
        </div>

        <div class="mt-8 grid justify-items-center gap-2 text-center sm:mt-[50px]" aria-live="polite">
            <h1 class="text-xl font-medium text-primary sm:text-2xl" data-when="progress" @if ($phase !== 'progress') hidden @endif>{{ __('platform::register.provisioning.title') }}</h1>
            <h1 class="reg-pop text-xl font-medium text-primary sm:text-2xl" data-when="success" @if ($phase !== 'success') hidden @endif>{{ __('platform::register.provisioning.success_title') }}</h1>
            <h1 class="reg-pop text-xl font-medium text-danger sm:text-2xl" data-when="failed" @if ($phase !== 'failed') hidden @endif>{{ __('platform::register.provisioning.failed_title') }}</h1>
            <p class="text-sm text-(--reg-gray)" data-when="progress" @if ($phase !== 'progress') hidden @endif>{{ __('platform::register.provisioning.auto_refresh') }}</p>
        </div>

        {{-- Checklists (Figma frame 12:42310: two columns of 236, 742 wide) --}}
        <div class="mt-10 grid w-full max-w-[742px] gap-8 sm:mt-[50px] sm:grid-cols-[236px_236px] sm:justify-between" data-when-not="failed" @if ($phase === 'failed') hidden @endif role="list" aria-label="{{ __('platform::register.provisioning.progress') }}">
            @foreach ([['column_store', $storeSteps, 'gap-8'], ['column_language', $languageSteps, 'gap-8 sm:gap-10']] as [$heading, $items, $gap])
                <div class="grid content-start {{ $gap }}" role="listitem" data-reveal="fade-up" @if ($loop->index) data-reveal-delay="120" @endif>
                    <p class="text-xl font-medium text-ink sm:text-2xl">{{ __('platform::register.provisioning.'.$heading) }}</p>
                    <ul class="grid gap-6">
                        @foreach ($items as $item)
                            <li class="flex items-center gap-4" data-step="{{ $item[1] }}">
                                <span class="reg-tick grid size-8 shrink-0 place-items-center rounded-[32px] border-2 border-primary text-white" @if ($phase === 'success') data-done @endif aria-hidden="true">
                                    <x-ui::icon name="check" :size="20" />
                                </span>
                                <span class="text-lg text-(--reg-gray) sm:text-xl" @isset($item[2]) lang="{{ $item[2] }}" dir="auto" @endisset>{{ __('platform::register.provisioning.'.$item[0]) }}</span>
                                <span class="sr-only" data-step-state>{{ $phase === 'success' ? __('platform::register.steps.done') : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Success --}}
        <div class="reg-enter mt-12 grid w-full max-w-[520px] gap-4 rounded-[24px] bg-white p-5 text-center shadow-(--reg-card-shadow) sm:p-8" data-when="success" @if ($phase !== 'success') hidden @endif>
            <p class="text-base text-(--reg-gray)">{{ __('platform::register.provisioning.success_text') }}</p>
            <div class="grid gap-1 rounded-xl bg-(--reg-tint) px-4 py-3">
                <span class="text-[11px] leading-[15px] text-(--reg-muted)">{{ __('platform::register.provisioning.store_address') }}</span>
                <a href="https://{{ $storeHost }}" class="text-[15px] font-medium break-all text-ink hover:text-primary" dir="ltr" target="_blank" rel="noopener">{{ $storeHost }}</a>
            </div>
            @if ($customDomain)
                <p class="text-[13px] leading-[18px] text-(--reg-muted)">{{ __('platform::register.provisioning.custom_domain_note', ['domain' => $customDomain]) }}</p>
            @endif
            <a href="{{ route('central.tenant.login', ['store' => $tenant->slug]) }}" class="group inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-7 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99]" data-panel-link>
                {{ __('platform::register.provisioning.go_panel') }}
                <x-ui::icon name="chevron-right" :size="18" class="transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
            </a>
        </div>

        {{-- Failure: the technical error is never shown here --}}
        <div class="reg-enter mt-8 grid w-full max-w-[520px] gap-4 rounded-[24px] bg-white p-5 text-center shadow-(--reg-card-shadow) sm:p-8" data-when="failed" @if ($phase !== 'failed') hidden @endif role="alert">
            <p class="text-base text-ink">{{ __('platform::register.provisioning.failed_text') }}</p>
            <p class="text-sm text-(--reg-gray)">{{ __('platform::register.provisioning.failed_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <a href="{{ request()->fullUrl() }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-primary px-5 text-sm font-medium text-primary transition hover:bg-(--reg-tint)">
                    <x-ui::icon name="refresh" :size="16" />{{ __('platform::register.provisioning.reload') }}
                </a>
                @if ($mailto)
                    <a href="{{ $mailto }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-medium text-white transition hover:bg-primary-hover">
                        <x-ui::icon name="mail" :size="16" />{{ __('platform::register.provisioning.contact') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
