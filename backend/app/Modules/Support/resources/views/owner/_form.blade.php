{{-- New ticket from the owner portal (OWN-009): store picker (or account) + the shared fields. $stores, $categories, $priorities. --}}
<form id="owner-ticket-form" method="POST" action="{{ route('owner.support.tickets.store') }}" enctype="multipart/form-data" class="grid gap-4" data-ajax novalidate
    data-modal-title="{{ __('support::tickets.new_ticket_title') }}"
    data-modal-subtitle="{{ __('support::tickets.new_ticket_subtitle') }}"
    data-submit-label="{{ __('support::tickets.thread.send') }}">
    @csrf
    <x-ui::select name="tenant_id" :label="__('support::tickets.fields.store')" :options="$stores" :selected="count($stores) === 1 ? array_key_first($stores) : null" :placeholder="__('support::tickets.account_ticket')" :helper="__('support::tickets.fields.store_helper')" :search="count($stores) > 6" allow-clear />
    @include('support::tickets._form-fields')
</form>
