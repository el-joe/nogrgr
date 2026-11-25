{{-- Relative date ("2 h ago") with the full date as tooltip; $empty when there is no date. --}}
@if ($date)
    <time datetime="{{ $date->toIso8601String() }}" title="{{ $date->locale(app()->getLocale())->translatedFormat('j M Y, H:i') }}" class="whitespace-nowrap text-ink">{{ $date->locale(app()->getLocale())->diffForHumans() }}</time>
@else
    <span class="text-ink-secondary">{{ $empty ?? '—' }}</span>
@endif
