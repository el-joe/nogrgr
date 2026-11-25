{{-- Relative time with the full date as tooltip. --}}
@if ($date)
    <time datetime="{{ $date->toIso8601String() }}" title="{{ $date->locale(app()->getLocale())->translatedFormat('j M Y, H:i') }}" class="whitespace-nowrap text-ink-secondary">{{ $date->locale(app()->getLocale())->diffForHumans() }}</time>
@else
    <span class="text-ink-secondary">—</span>
@endif
