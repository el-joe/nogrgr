{{-- "ER-311" + product name and SKU. --}}
@php
    $names = json_decode((string) $request->getAttribute('product_name'), true);
    $productName = is_array($names) ? ($names[app()->getLocale()] ?? reset($names) ?: '') : (string) $request->getAttribute('product_name');
@endphp
<a href="{{ route('admin.requests.edits.show', $request->id) }}" class="grid min-w-0 max-w-[320px] rounded-control">
    <span class="truncate font-medium text-ink"><bdi dir="ltr">{{ $request->reference() }}</bdi> · {{ $productName }}</span>
    <bdi dir="ltr" class="truncate text-xs text-ink-secondary">{{ $request->getAttribute('product_sku') }}</bdi>
</a>
