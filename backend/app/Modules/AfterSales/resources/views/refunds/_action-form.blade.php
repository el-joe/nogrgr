{{-- ADM-024 / VEN-023 retry / mark completed / reject (Figma 70:41386 "Retry refund"). --}}
<form id="refund-action-form" method="POST" action="{{ $formAction }}" data-ajax class="grid gap-4"
    data-modal-title="{{ __('after-sales::refunds.actions.'.$action) }} · {{ $refund->reference }}">
    @csrf
    <input type="hidden" name="action" value="{{ $action }}">
    @if ($refund->failure_reason)
        <p class="flex items-start gap-2 rounded-control bg-danger-bg px-3 py-2 text-sm text-danger" role="alert"><x-ui::icon name="alert-triangle" :size="16" class="mt-0.5 shrink-0" />{{ $refund->failure_reason }}</p>
    @endif
    <p class="text-sm text-ink-secondary">{{ __('after-sales::refunds.actions_help.'.$action, ['amount' => $refund->money()->format()]) }}</p>
    <x-ui::textarea name="note" :label="__('after-sales::refunds.fields.note')" rows="3" maxlength="1000" :required="$action === 'reject'" />
    @include('commerce::partials.modal-actions', ['submit' => __('after-sales::refunds.actions.'.$action), 'tone' => $action === 'reject' ? 'danger' : 'primary', 'icon' => ['retry' => 'refresh', 'complete' => 'check', 'reject' => 'x-circle'][$action]])
</form>
