{{--
    Pending approval notice after sign-up (AFF-003, Figma Affiliate & Owner 119:100433):
    confirm the e-mail, the team reviews the account, MAIL-038 arrives on activation.
    Inputs: $surface, $email.
--}}
@extends('identity::auth._panel', ['title' => __('affiliates::panel.pending.title')])

@section('card')
    <div class="grid justify-items-center text-center" data-affiliate-pending>
        <span class="mb-4 grid size-14 place-items-center rounded-full bg-warning-bg text-warning">
            <x-ui::icon name="clock" :size="28" />
        </span>
        <h1 id="auth-title" class="text-2xl font-semibold leading-8 text-ink">{{ __('affiliates::panel.pending.title') }}</h1>
        <p class="mt-2 text-sm text-ink-secondary">{{ __('affiliates::panel.pending.text') }}</p>
        <p class="mt-4 rounded-control bg-subtle px-4 py-2 text-sm font-medium text-ink" dir="ltr">{{ $email }}</p>
    </div>

    <ol class="mt-6 grid gap-3 text-sm text-ink">
        @foreach (['verify', 'review', 'activate'] as $step)
            <li class="flex items-start gap-3">
                <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-subtle text-xs font-semibold text-ink-brand" dir="ltr">{{ $loop->iteration }}</span>
                <span>{{ __('affiliates::panel.pending.steps.'.$step) }}</span>
            </li>
        @endforeach
    </ol>

    <form method="POST" action="{{ route('affiliate.verification.resend') }}" class="mt-6">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <x-ui::button type="submit" variant="secondary" block icon="mail">{{ __('affiliates::panel.verify.resend') }}</x-ui::button>
    </form>
    <x-ui::button :href="route('affiliate.login')" variant="ghost" block class="mt-3">{{ __('affiliates::panel.register.login') }}</x-ui::button>
@endsection
