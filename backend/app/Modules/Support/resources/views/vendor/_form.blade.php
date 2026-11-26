{{-- New support ticket (VEN-091a): modal fragment and the body of the full page. $categories, $priorities. --}}
<form id="support-ticket-form" method="POST" action="{{ vendor_route('support.store') }}" enctype="multipart/form-data" class="grid gap-4" data-ajax novalidate
    data-modal-title="{{ __('support::tickets.new_ticket_title') }}"
    data-modal-subtitle="{{ __('support::tickets.new_ticket_subtitle') }}"
    data-submit-label="{{ __('support::tickets.thread.send') }}">
    @csrf
    @include('support::tickets._form-fields')
</form>
