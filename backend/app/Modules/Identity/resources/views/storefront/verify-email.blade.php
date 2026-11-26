{{--
    Verify e-mail ({store}/account/verify-email, SF-ECO-025c, Figma Storefront additions
    114:107621 / m 114:107802): six digit boxes (paste / auto-advance), verify, resend with a
    countdown, change e-mail (→ profile). Inputs: $customer, $redirect, $resendIn, $minutes.
--}}
@php
    [$local, $domain] = array_pad(explode('@', (string) $customer->email, 2), 2, '');
    $masked = mb_substr($local, 0, 1).'•••••'.($domain !== '' ? '@'.$domain : '');
@endphp
<x-sf::layout :title="__st('auth.verify_title')" :mobile-title="__st('auth.verify_title')" :back="route('storefront.account.profile')" nav="account" page="verify-email">
    <div class="mx-auto grid w-full max-w-[420px] justify-items-center gap-4 px-4 py-10 text-center sm:py-[72px]" data-verify-email data-reveal="fade-up">
        <span class="grid size-16 place-items-center rounded-full bg-sf-accent-subtle text-sf-accent motion-safe:animate-[mo-pop_400ms_cubic-bezier(.22,1,.36,1)_both]" aria-hidden="true"><x-ui::icon name="mail" :size="28" /></span>
        <h1 class="text-2xl font-semibold leading-8 text-sf-ink">{{ __st('auth.verify_title') }}</h1>
        <p class="text-xs leading-4 text-sf-muted">{{ __st('auth.verify_text', ['email' => $masked]) }}</p>

        <form method="POST" action="{{ route('storefront.account.verification.verify') }}" class="grid w-full justify-items-center gap-4" data-ajax data-verify-form novalidate>
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirect }}">
            <input type="hidden" name="code" value="" data-verify-code>
            <fieldset class="grid justify-items-center gap-2">
                <legend class="sr-only">{{ __st('auth.code_label') }}</legend>
                <div class="flex gap-2 [direction:ltr] sm:gap-3" data-verify-digits>
                    @foreach (range(1, 6) as $digit)
                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="{{ $digit === 1 ? 'one-time-code' : 'off' }}" aria-label="{{ __st('auth.digit', ['number' => $digit]) }}" class="size-11 rounded-lg border border-sf-border bg-sf-surface text-center text-lg font-medium text-sf-ink outline-none transition focus:border-sf-accent focus:ring-2 focus:ring-sf-accent/20 aria-invalid:border-sf-danger sm:h-[52px] sm:w-11" data-verify-digit @if ($digit === 1) autofocus @endif>
                    @endforeach
                </div>
                <p class="sf-error" data-error-for="code" role="alert" hidden></p>
            </fieldset>
            <x-sf::form.button type="submit" size="lg" block :busy="__st('auth.verifying')" class="max-w-[360px]">{{ __st('auth.verify_submit') }}</x-sf::form.button>
        </form>

        <p class="text-xs text-sf-muted">{{ __st('auth.no_code') }}
            <button type="button" class="font-medium text-sf-accent disabled:cursor-default disabled:opacity-100" data-verify-resend="{{ route('storefront.account.verification.resend') }}" data-seconds="{{ $resendIn }}" data-label="{{ __st('auth.resend') }}" data-label-wait="{{ __st('auth.resend_in', ['time' => ':time']) }}" @disabled($resendIn > 0)>{{ $resendIn > 0 ? __st('auth.resend_in', ['time' => sprintf('00:%02d', $resendIn)]) : __st('auth.resend') }}</button>
        </p>
        <a href="{{ route('storefront.account.profile') }}" class="sf-btn sf-btn-outline px-5 text-sm">{{ __st('auth.change_email') }}</a>
        <p class="text-[11px] leading-4 text-sf-muted">{{ __st('auth.expires', ['minutes' => $minutes]) }}</p>
    </div>
</x-sf::layout>
