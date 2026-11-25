{{-- "PR-2041 · Bamboo cutting board set" + secondary line (files / quantity / website). --}}
@php
    $kind = $request->kind();
    $secondary = match ($kind) {
        \App\Modules\Catalog\Enums\RequestKind::Manufacturing => trans_choice('catalog::requests.details.quantity', 1).': '.number_format((int) $request->quantity),
        \App\Modules\Catalog\Enums\RequestKind::Brand => $request->website,
        default => \Illuminate\Support\Str::limit((string) $request->description, 60),
    };
@endphp
<a href="{{ route($kind->routePrefix().'.show', $request->id) }}" class="grid min-w-0 max-w-[340px] rounded-control focus-visible:outline-2">
    <span class="truncate font-medium text-ink"><bdi dir="ltr">{{ $request->reference() }}</bdi> · {{ $request->subject() }}</span>
    @if (filled($secondary))
        <span class="truncate text-xs text-ink-secondary">{{ $secondary }}</span>
    @endif
</a>
