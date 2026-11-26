{{-- Order address snapshot (OrderAddressSnapshotData::toArray): name · phone, then one line. --}}
@props(['address' => null])
@php
    $address = is_array($address) ? $address : [];
    $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? ($address['country_iso2'] ?? null);
    $line = collect([$address['address_line1'] ?? null, $address['city'] ?? null, $address['state'] ?? null, $country])->filter()->implode(', ');
@endphp
<p {{ $attributes->class(['text-xs leading-4 text-sf-muted']) }}>
    @if (! empty($address['full_name']) || ! empty($address['phone']))
        <span class="block">{{ $address['full_name'] ?? '' }}@if (! empty($address['phone'])) · <span dir="ltr">{{ $address['phone'] }}</span>@endif</span>
    @endif
    <span class="block">{{ $line ?: '—' }}</span>
</p>
