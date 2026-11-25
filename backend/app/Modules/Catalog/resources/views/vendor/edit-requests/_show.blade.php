{{-- Edit request detail (VEN-013 view action): the field diff, reason and review result. --}}
<div class="grid gap-4" data-modal-title="ER-{{ $request->id }}" data-modal-subtitle="{{ $request->product?->name }}">
    <div class="flex items-center gap-2">@include('catalog::vendor.edit-requests._status-cell')</div>
    @foreach ($request->changes as $change)
        @php($field = \App\Modules\Catalog\Enums\EditableProductField::tryFrom((string) $change['field']))
        <div class="grid gap-2">
            <p class="text-sm font-medium text-ink">{{ $field?->label() ?? $change['field'] }}{{ $change['locale'] ? ' ('.strtoupper((string) $change['locale']).')' : '' }}</p>
            @if ($field === \App\Modules\Catalog\Enums\EditableProductField::Images)
                <p class="text-sm text-ink-secondary">{{ trans_choice('catalog::vendor.edit_requests.images_count', count((array) $change['requested']), ['count' => count((array) $change['requested'])]) }}</p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    <div class="rounded-control border border-line bg-subtle p-3 text-sm"><span class="block text-xs text-ink-secondary">− {{ __('catalog::vendor.edit_requests.current') }}</span>{{ is_scalar($change['current']) ? $change['current'] : '—' }}</div>
                    <div class="rounded-control border border-primary/30 bg-primary-subtle p-3 text-sm"><span class="block text-xs text-ink-brand">+ {{ __('catalog::vendor.edit_requests.requested') }}</span>{{ is_scalar($change['requested']) ? $change['requested'] : '—' }}</div>
                </div>
            @endif
        </div>
    @endforeach
    @if ($request->vendor_note)
        <div class="grid gap-1"><p class="text-xs font-medium text-ink-secondary">{{ __('catalog::vendor.edit_requests.why') }}</p><p class="text-sm text-ink">{{ $request->vendor_note }}</p></div>
    @endif
    @if ($request->reason)
        <div class="rounded-control bg-danger-bg p-3 text-sm text-danger">{{ __('catalog::vendor.edit_requests.rejected_reason') }}: {{ $request->reason }}</div>
    @endif
</div>
