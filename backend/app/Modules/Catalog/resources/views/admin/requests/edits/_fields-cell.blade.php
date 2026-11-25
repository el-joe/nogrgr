{{-- Changed fields as small badges (max 3 + "+n more"). --}}
@php
    $fields = collect($request->changes)->map(function (array $change): string {
        $field = \App\Modules\Catalog\Enums\EditableProductField::tryFrom((string) $change['field']);
        $label = $field?->label() ?? (string) $change['field'];

        return ! empty($change['locale']) ? $label.' ('.strtoupper((string) $change['locale']).')' : $label;
    })->unique()->values();
@endphp
<div class="flex flex-wrap gap-1">
    @foreach ($fields->take(3) as $label)
        <x-ui::badge tone="neutral" variant="outline">{{ $label }}</x-ui::badge>
    @endforeach
    @if ($fields->count() > 3)
        <span class="text-xs text-ink-secondary">{{ __('catalog::requests.edits.fields_more', ['count' => $fields->count() - 3]) }}</span>
    @endif
</div>
