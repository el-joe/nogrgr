{{--
    "Impersonating" banner (Vendor Panel new screens 82:101033): who is viewing the store,
    minutes left (live countdown, resources/js/panels/vendor/impersonation.js) and
    End session (back to the admin panel, audited).
--}}
@props(['impersonation', 'storeName' => null])
<div class="px-4 pt-6 sm:px-6" role="status" data-impersonation-banner data-ends-at="{{ $impersonation['ends_at']->toIso8601String() }}" data-label-one="{{ trans_choice('identity::vendor_auth.impersonation.ends_in', 1, ['count' => 1]) }}" data-label-many="{{ trans_choice('identity::vendor_auth.impersonation.ends_in', 2, ['count' => '__COUNT__']) }}">
    <div class="mx-auto flex w-full max-w-[1156px] flex-wrap items-center gap-3 rounded-card border border-warning/25 bg-warning-bg px-4 py-3 text-[13px] text-ink sm:flex-nowrap">
        <x-ui::icon name="alert-triangle" :size="20" class="shrink-0 text-warning" />
        <p class="min-w-0 flex-1">
            {{ __('identity::vendor_auth.impersonation.banner', ['store' => $storeName, 'admin' => $impersonation['admin_name']]) }}
            <span data-impersonation-remaining>{{ trans_choice('identity::vendor_auth.impersonation.ends_in', $impersonation['minutes_left'], ['count' => $impersonation['minutes_left']]) }}</span>
            {{ __('identity::vendor_auth.impersonation.logged') }}
        </p>
        <form method="POST" action="{{ $impersonation['leave_url'] }}" class="shrink-0">
            @csrf
            <x-ui::button type="submit" variant="secondary" size="sm" icon="log-out">{{ __('identity::vendor_auth.impersonation.leave') }}</x-ui::button>
        </form>
    </div>
</div>
