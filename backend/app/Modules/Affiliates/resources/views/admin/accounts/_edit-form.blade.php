{{-- ADM-060a "Edit affiliate — <name>" (Figma 74:68877): commission rate (program default / percentage / fixed) and status. --}}
@php
    $value = $affiliate->commission_value !== null ? number_format($affiliate->commission_value / 100, 2, '.', '') : null;
@endphp
<form id="affiliate-edit-form" method="POST" action="{{ route('admin.affiliates.update', $affiliate->id) }}" data-ajax novalidate class="grid gap-4"
    data-modal-title="{{ __('affiliates::admin.accounts.edit_title', ['name' => $affiliate->name]) }}">
    @csrf
    @method('PUT')
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::select name="commission_type" :label="__('affiliates::admin.accounts.fields.commission_type')" :options="$types" :selected="$affiliate->commission_type?->value" :placeholder="__('affiliates::admin.accounts.program_default', ['rate' => $defaultLabel])" :search="false" allow-clear />
        <x-ui::input name="commission_value" type="number" step="0.01" min="0" :value="$value" :label="__('affiliates::admin.accounts.fields.commission_value')" :helper="__('affiliates::admin.accounts.commission_help')" dir="ltr" />
    </div>
    <x-ui::select name="status" :label="__('affiliates::admin.accounts.fields.status')" :options="$statuses" :selected="$affiliate->status->value" :search="false" required />
    @include('commerce::partials.modal-actions', ['submit' => __('affiliates::admin.save'), 'cancel' => __('affiliates::admin.cancel')])
</form>
