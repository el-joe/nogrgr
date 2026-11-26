{{-- Invoice number, linked to the vendor invoice page when the staff may open invoices. --}}
@php $number = $purchase->getAttribute('invoice_number'); $id = $purchase->getAttribute('invoice_id'); @endphp
@if ($number && $id && $links)
    <a href="{{ vendor_route('finance.invoices.show', ['invoice' => $id]) }}" class="font-medium text-ink-brand hover:underline"><bdi dir="ltr">{{ $number }}</bdi></a>
@else
    <bdi dir="ltr">{{ $number ?? '—' }}</bdi>
@endif
