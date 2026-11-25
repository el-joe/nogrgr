{{--
    WEB-016a Social sign-in result (Figma Central Website additions: success 125:10900,
    cancelled 125:10912): shown on {central}/auth/{provider}/callback. Success continues
    automatically (JS, with a manual link); cancelled / error offer the way back.
    Inputs: $provider (SocialProvider), $state (success|cancelled|error), $message, $continueUrl.
--}}
<x-layouts.central :title="__('identity::social.title.'.$state, ['provider' => $provider->brand()])">
    <main id="main" class="reg-page grid min-h-screen place-items-center bg-white px-4 py-10 font-sans text-ink" data-page="register-social-result">
        <section class="reg-enter grid w-full max-w-[420px] justify-items-center gap-4 rounded-[24px] bg-white p-8 text-center shadow-(--reg-card-shadow)"
            @if ($state === 'success') data-social-success data-continue-url="{{ $continueUrl }}" aria-live="polite" @else role="alert" @endif>
            <p class="flex items-center gap-1.5 text-xs text-(--reg-gray)" dir="ltr">
                <x-ui::icon name="shield" :size="14" class="text-success" />{{ request()->getHost() }}/auth/{{ $provider->value }}/callback
            </p>
            @if ($state === 'success')
                <span class="reg-pop grid size-[78px] place-items-center rounded-full bg-success-bg text-success" aria-hidden="true"><x-ui::icon name="check-circle" :size="32" /></span>
                <h1 class="text-2xl leading-8 font-semibold text-ink">{{ __('identity::social.title.success', ['provider' => $provider->brand()]) }}</h1>
                <p class="text-sm text-(--reg-gray)">{{ __('identity::social.text.success') }}</p>
                <span class="reg-spinner size-8 rounded-full border-4 border-(--reg-ring) border-t-primary" aria-hidden="true"></span>
                <a href="{{ $continueUrl }}" class="text-sm font-medium text-primary hover:underline">{{ __('identity::social.continue') }}</a>
            @else
                <span class="reg-pop grid size-[78px] place-items-center rounded-full bg-danger-bg text-danger" aria-hidden="true"><x-ui::icon name="x-circle" :size="32" /></span>
                <h1 class="text-2xl leading-8 font-semibold text-ink">{{ __('identity::social.title.'.$state, ['provider' => $provider->brand()]) }}</h1>
                <p class="text-sm text-(--reg-gray)">{{ $message ?? __('identity::social.text.'.$state) }}</p>
                <a href="{{ $continueUrl }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-primary px-8 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.99]">{{ __('identity::social.back') }}</a>
            @endif
        </section>
    </main>
</x-layouts.central>
