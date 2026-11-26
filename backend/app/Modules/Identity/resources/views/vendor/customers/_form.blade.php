{{--
    Customer create / edit (VEN-025a, Figma Vendor Panel 84:107236 "New customer"), loaded
    into #vendor-customer-modal: first + last name, e-mail, phone (intl-tel-input, the
    visitor / customer country pre-selected), language and "Subscribe to newsletter".
--}}
<form
    id="vendor-customer-form"
    method="POST"
    action="{{ $customer->exists ? vendor_route('sales.customers.update', ['customer' => $customer->id]) : vendor_route('sales.customers.store') }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('identity::customers.save') }}"
>
    @csrf
    @if ($customer->exists)
        @method('PUT')
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::input name="first_name" :label="__('identity::customers.fields.first_name')" :value="$firstName" required maxlength="60" autocomplete="off" data-autofocus />
        <x-ui::input name="last_name" :label="__('identity::customers.fields.last_name')" :value="$lastName" maxlength="60" autocomplete="off" />
    </div>
    <x-ui::input name="email" type="email" :label="__('identity::customers.fields.email')" :value="$customer->email" maxlength="191" dir="ltr" autocomplete="off" />
    <x-ui::phone name="phone" id="vendor-customer-phone" :label="__('identity::customers.fields.phone')" :value="$customer->phone" :country="$customer->phone_country" :required="! $customer->exists" />
    <x-ui::select name="locale" :label="__('identity::customers.fields.locale')" :options="$locales" :selected="$customer->locale ?? app()->getLocale()" :search="false" />
    <x-ui::checkbox name="newsletter" :label="__('identity::customers.fields.newsletter')" :description="__('identity::customers.fields.newsletter_help')" :checked="$subscribed" unchecked-value="0" />
    @unless ($customer->exists)
        <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2.5 text-[13px] text-info">
            <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
            <span>{{ __('identity::customers.fields.no_password') }}</span>
        </p>
    @endunless
</form>
