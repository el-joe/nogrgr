@php($time = ($ticket->last_reply_at ?? $ticket->updated_at)?->locale(app()->getLocale()))
<time datetime="{{ $time?->toIso8601String() }}" title="{{ $time?->translatedFormat('j M Y, H:i') }}" class="whitespace-nowrap">{{ $time?->diffForHumans() }}</time>
