{{--
    Forgot password (Figma 127:12477, mobile 127:12586) and the "Email sent" state
    (127:12646) when session('status') is set: mail disc, resend countdown (re-posts the
    same e-mail) and back to login. Data: $seo, $action, session('status'|'email').
--}}
@php
    $sent = session()->has('status');
    $email = old('email', session('email'));
    $loginUrl = Route::has('central.login') ? route('central.login') : null;
@endphp
@if ($sent)
    <x-central::auth-shell :seo="$seo ?? null" :title="__('content::site.auth.sent_title')" :highlight="__('content::site.auth.sent_highlight')"
        :text="$email ? __('content::site.auth.sent_text', ['email' => $email]) : __('content::site.auth.sent_text_generic')" page="site-forgot">
        <div class="flex flex-col items-center gap-4 text-center" role="status">
            <span class="grid size-14 place-items-center rounded-full bg-success-bg text-success"><x-ui::icon name="mail" :size="26" /></span>
            <h2 class="text-base font-medium text-site-ink">{{ __('content::site.auth.sent_card') }}</h2>
            <form method="POST" action="{{ $action }}" class="w-full">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="site-btn h-11 w-full rounded-lg border border-site-border text-sm text-site-ink enabled:hover:border-primary enabled:hover:text-primary disabled:cursor-not-allowed"
                    data-resend="59" data-resend-template="{{ __('content::site.auth.resend_in', ['time' => ':time']) }}" data-resend-ready="{{ __('content::site.auth.resend') }}" @disabled(! $email)>
                    <span data-resend-label>{{ __('content::site.auth.resend') }}</span>
                </button>
            </form>
            @if ($loginUrl)
                <a href="{{ $loginUrl }}" class="site-link text-xs text-primary">{{ __('content::site.auth.back_to_login') }}</a>
            @endif
        </div>
    </x-central::auth-shell>
@else
    <x-central::auth-shell :seo="$seo ?? null" :title="__('content::site.auth.forgot_title')" :highlight="__('content::site.auth.forgot_highlight')" :text="__('content::site.auth.forgot_text')" page="site-forgot">
        <h2 class="text-base font-medium text-site-ink">{{ __('content::site.auth.forgot_card') }}</h2>
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4" novalidate>
            @csrf
            <x-central::auth-input name="email" type="email" :label="__('content::site.auth.email_address')" :value="$email" autocomplete="email" autofocus />
            <button type="submit" class="site-btn h-12 rounded-xl bg-primary text-base font-medium text-white hover:bg-primary-hover">{{ __('content::site.auth.send_link') }}</button>
        </form>
        @if ($loginUrl)
            <a href="{{ $loginUrl }}" class="site-link mt-5 inline-block text-xs text-primary">{{ __('content::site.auth.back_to_login') }}</a>
        @endif
    </x-central::auth-shell>
@endif
