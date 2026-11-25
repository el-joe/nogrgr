{{--
    One return transition modal (ADM-023a / VEN-022a, Figma 70:41386 "Complete inspection"):
    note / message, inspection result + condition + restock (+ refund now), refund amount
    (≤ max refundable), exchange tracking number.
--}}
@use('App\Modules\AfterSales\Enums\ReturnAction')
@php
    $danger = in_array($action, [ReturnAction::Reject], true);
    $noteRequired = in_array($action, [ReturnAction::Reject, ReturnAction::RequestInfo], true);
    $noteLabel = $action === ReturnAction::RequestInfo ? __('after-sales::returns.fields.message') : __('after-sales::returns.fields.note');
@endphp
<form id="return-action-form" method="POST" action="{{ $formAction }}" data-ajax enctype="multipart/form-data" class="grid gap-4"
    data-modal-title="{{ $action->label() }} · {{ $return->reference }}"
    x-data="{ result: 'accepted', refundNow: {{ $canRefundNow ? 'true' : 'false' }} }">
    @csrf
    <p class="text-sm text-ink-secondary">{{ __('after-sales::returns.actions_help.'.$action->value) }}</p>

    @if ($action === ReturnAction::Inspect)
        <x-ui::select name="result" :label="__('after-sales::returns.fields.result')" :options="$results" selected="accepted" :search="false" required x-on:change="result = $event.target.value" />
        <x-ui::input name="condition" :label="__('after-sales::returns.fields.condition')" maxlength="120" :placeholder="__('after-sales::returns.condition_placeholder')" />
        <div x-show="result === 'accepted'" class="grid gap-4">
            <x-ui::switch name="restock" :label="__('after-sales::returns.fields.restock')" checked />
            @if ($branches !== [])
                <x-ui::select name="branch_id" :label="__('after-sales::returns.fields.branch')" :options="$branches" :selected="$branchId" allow-clear :placeholder="__('after-sales::returns.branch_auto')" />
            @endif
            @if ($canRefundNow)
                <x-ui::switch name="then_refund" :label="__('after-sales::returns.fields.refund_now')" checked x-on:change="refundNow = $event.target.checked" />
                <div x-show="refundNow"><x-ui::input name="amount" type="number" step="0.01" min="0.01" :max="$maxRefund->toDecimal()" :value="$maxRefund->toDecimal()" :label="__('after-sales::refunds.fields.amount')" :helper="__('after-sales::returns.max_refundable', ['amount' => $maxRefund->format()])" dir="ltr" /></div>
            @endif
        </div>
        <x-ui::file-upload name="photos[]" multiple accept="image/*" :label="__('after-sales::returns.fields.photos')" :hint="__('after-sales::returns.photos_hint')" />
    @endif

    @if ($action === ReturnAction::Refund)
        <x-ui::input name="amount" type="number" step="0.01" min="0.01" :max="$maxRefund->toDecimal()" :value="$maxRefund->toDecimal()" :label="__('after-sales::refunds.fields.amount')" :helper="__('after-sales::returns.max_refundable', ['amount' => $maxRefund->format()])" required dir="ltr" />
    @endif

    @if ($action === ReturnAction::ShipExchange)
        <x-ui::input name="tracking_number" :label="__('after-sales::returns.fields.tracking')" maxlength="64" required dir="ltr" />
    @endif

    <x-ui::textarea name="note" :label="$action === ReturnAction::Inspect ? __('after-sales::returns.fields.inspector_notes') : $noteLabel" rows="3" maxlength="2000" :required="$noteRequired" />

    @include('commerce::partials.modal-actions', ['submit' => $action->label(), 'tone' => $danger ? 'danger' : 'primary'])
</form>
