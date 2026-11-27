{{-- ADM-021c create return (Figma 70:41386): order line, quantity, type, reason, description ≥ 10 characters. --}}
@php
    $lineOptions = [];
    foreach ($lines as $id => $line) {
        $lineOptions[$id] = $line['item']->name.($line['item']->variant_label ? ' · '.$line['item']->variant_label : '').' × '.$line['available'];
    }
    $max = max(1, (int) collect($lines)->max('available'));
@endphp
<form id="order-return-form" method="POST" action="{{ $action }}" data-ajax novalidate class="grid gap-4" data-modal-title="{{ __('after-sales::returns.create_title') }}">
    @csrf
    @if ($lines === [])
        <x-ui::empty-state compact icon="box" :title="__('after-sales::returns.nothing_returnable')" />
    @else
        <x-ui::select name="order_item_id" :label="__('after-sales::returns.fields.item')" :options="$lineOptions" :selected="array_key_first($lineOptions)" :search="false" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui::input name="quantity" type="number" min="1" :max="$max" value="1" :label="__('after-sales::returns.fields.quantity')" required />
            <x-ui::select name="type" :label="__('after-sales::returns.fields.type')" :options="$types" selected="return" :search="false" />
        </div>
        <x-ui::select name="reason_code" :label="__('after-sales::returns.fields.reason')" :options="$reasons" :placeholder="__('after-sales::returns.reason_placeholder')" :search="false" required />
        <x-ui::textarea name="description" :label="__('after-sales::returns.fields.description_min')" rows="4" maxlength="2000" minlength="10" required />
    @endif
    @include('commerce::partials.modal-actions', ['submit' => __('after-sales::returns.create'), 'icon' => 'return'])
</form>
