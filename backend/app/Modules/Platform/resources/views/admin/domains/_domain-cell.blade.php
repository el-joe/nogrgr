{{-- Domain requests › Domain: host + "requested …" / "removed …" (links to the detail). --}}
@php
    $removed = $request->status === \App\Modules\Platform\Enums\DomainRequestStatus::Removed && $request->removed_at;
    $date = $removed ? $request->removed_at : $request->created_at;
    $when = $date ? ($date->gt(now()->subWeek()) ? $date->locale(app()->getLocale())->diffForHumans() : $date->locale(app()->getLocale())->translatedFormat('j M')) : '—';
@endphp
<a href="{{ route('admin.vendors.domains.show', $request->id) }}" class="grid min-w-0 hover:underline">
    <span class="truncate font-medium text-ink" dir="ltr">{{ $request->host }}</span>
    <span class="truncate text-xs text-ink-secondary">{{ __($removed ? 'platform::domains.removed_at' : 'platform::domains.requested_at', ['date' => $when]) }}</span>
</a>
