{{--
    "Assign plan manually" form (subscriptions page; reusable on the vendor profile by
    passing $tenantId to fix the store). Posts to admin.billing.subscriptions.assign.
--}}
<form id="assign-plan-form" method="POST" action="{{ route('admin.billing.subscriptions.assign') }}" data-ajax class="grid gap-5" novalidate>
    @csrf
    @if ($tenantId)
        <input type="hidden" name="tenant_id" value="{{ $tenantId }}">
    @else
        <x-ui::select name="tenant_id" id="assign-tenant" :label="__('billing::subscriptions.fields.store')" :ajax-url="route('admin.billing.subscriptions.tenants')" :placeholder="__('billing::subscriptions.fields.store_placeholder')" required />
    @endif
    <x-ui::select name="plan_id" id="assign-plan-id" :label="__('billing::subscriptions.fields.plan')" :options="$activePlans" :placeholder="__('billing::subscriptions.fields.plan_placeholder')" required />
    <x-ui::radio-group name="term" :label="__('billing::subscriptions.fields.term')" :options="$terms" value="monthly" required />
    <x-ui::switch name="mark_paid" id="assign-mark-paid" :label="__('billing::subscriptions.fields.mark_paid')" :description="__('billing::subscriptions.fields.mark_paid_hint')" checked />
</form>
