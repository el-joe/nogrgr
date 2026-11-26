{{-- Two-line cell: $title (bold) and $subtitle (muted, optional, LTR when $ltr). --}}
<span class="block font-medium text-ink">{{ $title }}</span>
@if (filled($subtitle ?? null))
    <span class="block text-xs text-ink-secondary" @if ($ltr ?? false) dir="ltr" @endif>{{ $subtitle }}</span>
@endif
