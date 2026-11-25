{{-- "Add Kuwait, Bahrain, remove Toys". --}}
@php
    $parts = [];

    if ($added !== []) {
        $parts[] = __('catalog::requests.tenant_changes.add_line', ['items' => implode(', ', $added)]);
    }

    if ($removed !== []) {
        $parts[] = __('catalog::requests.tenant_changes.remove_line', ['items' => implode(', ', $removed)]);
    }
@endphp
<span class="line-clamp-2 max-w-[360px] text-ink">{{ $parts === [] ? '—' : implode(app()->getLocale() === 'ar' ? '، ' : ', ', $parts) }}</span>
