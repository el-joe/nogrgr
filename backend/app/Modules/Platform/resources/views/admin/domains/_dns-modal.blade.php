{{--
    DNS record template create / edit modal (ADM-045b). One form: data-dns-edit buttons
    fill it (PUT to the record URL), data-dns-new resets it (POST). Priority is shown
    and required for MX only; TTL 60–86400.
--}}
@php
    $types = collect(\App\Modules\Platform\Enums\DnsRecordType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all();
@endphp
<x-ui::modal id="dns-record-modal" :title="__('platform::domains.dns.create_title')" :subtitle="__('platform::domains.dns.modal_subtitle')" size="md">
    <form
        id="dns-record-form"
        method="POST"
        action="{{ route('admin.vendors.domains.dns.store') }}"
        data-ajax
        data-dns-form
        data-store-url="{{ route('admin.vendors.domains.dns.store') }}"
        data-title-create="{{ __('platform::domains.dns.create_title') }}"
        data-title-edit="{{ __('platform::domains.dns.edit_title') }}"
        class="grid gap-5 sm:grid-cols-2"
        novalidate
    >
        @csrf
        <input type="hidden" name="_method" value="POST" data-dns-method>
        <x-ui::select name="type" :label="__('platform::domains.dns.fields.type')" :options="$types" selected="CNAME" :search="false" required />
        <x-ui::input name="ttl" type="number" :label="__('platform::domains.dns.fields.ttl')" value="3600" min="60" max="86400" required dir="ltr" :helper="__('platform::domains.dns.ttl_helper')" />
        <x-ui::input name="host" :label="__('platform::domains.dns.fields.host')" value="" required maxlength="253" dir="ltr" autocomplete="off" spellcheck="false" :helper="__('platform::domains.dns.host_helper')" data-autofocus />
        <x-ui::input name="value" :label="__('platform::domains.dns.fields.value')" value="" required maxlength="500" dir="ltr" autocomplete="off" spellcheck="false" :helper="__('platform::domains.dns.value_helper')" />
        <div data-priority-field hidden>
            <x-ui::input name="priority" type="number" :label="__('platform::domains.dns.fields.priority')" value="" min="0" max="65535" dir="ltr" :helper="__('platform::domains.dns.priority_helper')" />
        </div>
        <x-ui::input name="sort_order" type="number" :label="__('platform::domains.dns.fields.sort_order')" value="100" min="0" max="65535" required dir="ltr" />
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="dns-record-form" icon="check">{{ __('platform::domains.dns.save') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
