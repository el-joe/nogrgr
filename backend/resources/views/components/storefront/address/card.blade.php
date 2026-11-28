{{--
    Address card (spec §2). mode: manage (account: edit, set default, delete) |
    select (checkout radio card: radio + edit). $address = CustomerAddressResource array.
    The same markup is cloned by the address-book script from <template data-address-template>.
--}}
@props(['address', 'mode' => 'manage', 'group' => 'shipping_address_id', 'selected' => false, 'editIcon' => 'edit', 'editSize' => 18])
<article class="sf-address-card p-4" data-address-card="{{ $address['id'] }}">
    <div class="flex items-start gap-3">
        @if ($mode === 'select')
            <input type="radio" class="sf-radio mt-0.5" name="{{ $group }}" value="{{ $address['id'] }}" id="{{ $group }}-{{ $address['id'] }}" @checked($selected) data-address-radio>
        @endif
        <label @if ($mode === 'select') for="{{ $group }}-{{ $address['id'] }}" @endif class="grid min-w-0 flex-1 cursor-pointer gap-1">
            <span class="flex flex-wrap items-center gap-2">
                <strong class="text-sm font-medium text-sf-ink" dir="auto" data-field-full-name>{{ $address['full_name'] }}</strong>
                <span class="sf-chip sf-chip-label" data-field-label @if (! $address['label']) hidden @endif>{{ $address['label'] }}</span>
                <span class="sf-chip sf-chip-default" data-field-default @if (! $address['is_default']) hidden @endif>{{ __('identity::addresses.default') }}</span>
            </span>
            <span class="text-xs text-sf-muted" dir="ltr" data-field-phone>{{ $address['phone'] }}</span>
            <span class="text-sm text-sf-ink [[dir=ltr]_&]:text-left [[dir=rtl]_&]:text-right" dir="auto" data-field-one-line>{{ $address['one_line'] }}</span>
        </label>
        <div class="flex shrink-0 items-center gap-3">
            <button type="button" class="grid size-8 place-items-center text-sf-muted hover:text-sf-ink" data-address-edit="{{ $address['id'] }}" aria-label="{{ __('identity::addresses.edit_title') }}" title="{{ __('identity::addresses.edit_title') }}"><x-ui::icon :name="$editIcon" :size="$editSize" /></button>
            @if ($mode === 'manage')
                <button type="button" class="text-xs font-medium text-sf-accent hover:underline" data-address-default="{{ $address['id'] }}" @if ($address['is_default']) hidden @endif>{{ __('identity::addresses.set_default') }}</button>
                <button type="button" class="grid size-8 place-items-center text-sf-danger" data-address-delete="{{ $address['id'] }}" aria-label="{{ __('identity::addresses.delete') }}" title="{{ __('identity::addresses.delete') }}"><x-ui::icon name="trash" :size="18" /></button>
            @endif
        </div>
    </div>
</article>
