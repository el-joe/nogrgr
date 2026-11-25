{{--
    Admin › Vendors › Pending registrations › detail: applicant, plan / term, progress
    (payment → e-mail verification → store details), completion link (sent at, count,
    expiry) with "Resend link" (vendors.manage, only when the status allows) and Delete
    (vendors.delete).
--}}
@use('App\Modules\Platform\Enums\RegistrationStep')
@php
    $admin = auth('admin')->user();
    $canResend = (bool) $admin?->can('vendors.manage') && $registration->status->canSendLink();
    $canDelete = (bool) $admin?->can('vendors.delete') && $registration->status->isPending();
    $locale = app()->getLocale();
    $date = fn (?\Illuminate\Support\Carbon $value) => $value?->locale($locale)->translatedFormat('j M Y, H:i');
    $name = $registration->applicantName();
    $steps = [RegistrationStep::Payment, RegistrationStep::EmailVerification, RegistrationStep::StoreDetails];
    $current = array_search($registration->status->step(), $steps, true);
    $current = $current === false ? count($steps) : $current;
    $rows = [
        'applicant' => [
            __('platform::registrations.fields.name') => $registration->owner_name ?? __('platform::registrations.not_given'),
            __('platform::registrations.fields.email') => ['ltr' => $registration->email],
            __('platform::registrations.fields.phone') => $registration->phone ? ['ltr' => $registration->phone] : __('platform::registrations.not_given'),
            __('platform::registrations.fields.language') => $registration->locale ? strtoupper($registration->locale) : __('platform::registrations.not_given'),
        ],
        'registration' => [
            __('platform::registrations.fields.plan') => $registration->plan?->name ?? __('platform::registrations.no_plan'),
            __('platform::registrations.fields.term') => $registration->term?->label() ?? __('platform::registrations.not_given'),
            __('platform::registrations.fields.store') => $registration->store_name ?? __('platform::registrations.no_store_yet'),
            __('platform::registrations.fields.slug') => $registration->slug ? ['ltr' => $registration->slug] : __('platform::registrations.no_store_yet'),
            __('platform::registrations.fields.started') => $date($registration->created_at) ?? '—',
            __('platform::registrations.fields.email_verified') => $date($registration->email_verified_at) ?? __('platform::registrations.not_yet'),
        ],
    ];
@endphp
<x-layouts.panel surface="admin" :title="$name" data-page="admin-vendor-reviews">
    <div class="grid gap-6" data-reviews="registration" data-reviews-reload>
        <x-ui::page-header
            :title="$name"
            :subtitle="$registration->email"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::registrations.title') => route('admin.vendors.pending'), $name => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$registration->status->tone()">{{ $registration->status->label() }}</x-ui::badge>
            </x-slot:status>
            @if ($canResend || $canDelete)
                <x-slot:actions>
                    @if ($canDelete)
                        <x-ui::button
                            variant="secondary"
                            icon="trash"
                            data-no-reload
                            :data-confirm="__('platform::registrations.actions.delete_message')"
                            :data-confirm-title="__('platform::registrations.actions.delete_title', ['email' => $registration->email])"
                            :data-confirm-label="__('platform::registrations.actions.delete')"
                            data-confirm-method="DELETE"
                            :data-confirm-url="route('admin.vendors.pending.destroy', ['registration' => $registration->id, 'redirect' => 1])"
                        >{{ __('platform::registrations.actions.delete') }}</x-ui::button>
                    @endif
                    @if ($canResend)
                        <x-ui::button
                            icon="mail"
                            :data-confirm="__('platform::registrations.actions.resend_message', ['email' => $registration->email])"
                            :data-confirm-title="__('platform::registrations.actions.resend_title')"
                            :data-confirm-label="__('platform::registrations.actions.resend')"
                            data-confirm-tone="primary"
                            data-confirm-method="POST"
                            :data-confirm-url="route('admin.vendors.pending.resend', $registration->id)"
                        >{{ __('platform::registrations.actions.resend') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <x-ui::card :title="__('platform::registrations.progress')" id="registration-progress">
            <ol class="grid gap-3 sm:grid-cols-3">
                @foreach ($steps as $index => $step)
                    @php
                        $state = $index < $current ? 'done' : ($index === $current ? 'current' : 'upcoming');
                    @endphp
                    <li @class([
                        'flex items-center gap-3 rounded-control border px-4 py-3',
                        'border-success bg-success-bg' => $state === 'done',
                        'border-primary bg-primary-subtle' => $state === 'current',
                        'border-line' => $state === 'upcoming',
                    ]) @if ($state === 'current') aria-current="step" @endif>
                        <span @class([
                            'grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold',
                            'bg-success text-on-primary' => $state === 'done',
                            'bg-primary text-on-primary' => $state === 'current',
                            'bg-subtle text-ink-secondary' => $state === 'upcoming',
                        ])>
                            @if ($state === 'done')
                                <x-ui::icon name="check" :size="16" />
                            @else
                                {{ \Illuminate\Support\Number::format($index + 1, locale: \App\Shared\Support\Locale::numberLocale()) }}
                            @endif
                        </span>
                        <span class="grid min-w-0">
                            <span class="truncate font-medium text-ink">{{ $step->label() }}</span>
                            <span class="text-xs text-ink-secondary">{{ __('platform::registrations.step_state.'.$state) }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
            @if ($registration->status === \App\Modules\Platform\Enums\RegistrationStatus::PaymentFailed)
                <p class="mt-4 flex items-center gap-2 rounded-control bg-danger-bg px-4 py-3 text-[13px] text-ink" role="status">
                    <x-ui::icon name="alert-triangle" :size="18" class="text-danger" />
                    {{ __('platform::registrations.payment_failed_hint') }}
                </p>
            @endif
        </x-ui::card>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_350px]">
            @foreach ($rows as $key => $fields)
                <x-ui::card :title="__('platform::registrations.cards.'.$key)" :id="'registration-'.$key">
                    <dl class="grid gap-3 text-sm">
                        @foreach ($fields as $label => $value)
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-ink-secondary">{{ $label }}</dt>
                                <dd class="min-w-0 break-words text-end font-medium text-ink">
                                    @if (is_array($value))
                                        <bdi dir="ltr">{{ $value['ltr'] }}</bdi>
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-ui::card>
            @endforeach

            <x-ui::card :title="__('platform::registrations.cards.link')" id="registration-link">
                <dl class="grid gap-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::registrations.fields.link_sent_at') }}</dt>
                        <dd class="text-end font-medium text-ink">{{ $date($registration->link_sent_at) ?? __('platform::registrations.never_sent') }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::registrations.fields.link_sent_count') }}</dt>
                        <dd class="text-end font-medium text-ink">{{ \Illuminate\Support\Number::format($registration->link_sent_count, locale: \App\Shared\Support\Locale::numberLocale()) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::registrations.fields.expires_at') }}</dt>
                        <dd class="text-end font-medium text-ink">
                            @if ($registration->expires_at)
                                {{ $date($registration->expires_at) }}
                                @if ($registration->isExpired())
                                    <x-ui::badge tone="danger" class="ms-1">{{ __('platform::registrations.expired') }}</x-ui::badge>
                                @endif
                            @else
                                {{ __('platform::registrations.never_sent') }}
                            @endif
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-ink-secondary">
                    {{ $registration->status->canSendLink()
                        ? trans_choice('platform::registrations.link_hint', $linkHours, ['hours' => $linkHours])
                        : __('platform::registrations.link_blocked') }}
                </p>
            </x-ui::card>
        </div>
    </div>
</x-layouts.panel>
