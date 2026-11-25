@php
    $names = $attribute->options->take($preview)->map(fn ($option) => $option->name)->filter()->implode(', ');
    $more = max(0, (int) $attribute->options_count - $preview);
@endphp
@if ($attribute->options_count === 0)
    <span class="text-ink-muted">{{ __('catalog::attributes.no_options') }}</span>
@else
    <span class="text-ink">{{ $names }}@if ($more > 0) <span class="text-ink-secondary" dir="ltr">{{ __('catalog::attributes.more_options', ['count' => $more]) }}</span>@endif</span>
@endif
