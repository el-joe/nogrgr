{{-- Customer column of VEN-025: name (links to the detail) and e-mail. --}}
<a href="{{ vendor_route('sales.customers.show', ['customer' => $customer->id]) }}" class="grid min-w-0 gap-0.5">
    <span class="truncate font-semibold text-ink hover:text-ink-brand">{{ $customer->name }}</span>
    @if ($customer->email)
        <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $customer->email }}</span>
    @endif
</a>
