{{--
    "Verified 2 h ago" / failure reason line under a card (updated live by integrations.js).
    @param string $key
    @param \App\Modules\Integrations\DTOs\ConnectionTestResult|null $last
--}}
<p class="flex items-center gap-1.5 text-xs" data-status-note="{{ $key }}" aria-live="polite">
    @if ($last === null)
        <span class="text-ink-muted">{{ __('integrations::admin.test.never') }}</span>
    @elseif ($last->ok)
        <x-ui::icon name="check-circle" :size="14" class="text-success" />
        <span class="text-success">{{ __('integrations::admin.test.verified_ago', ['time' => \Illuminate\Support\Carbon::parse($last->testedAt)->diffForHumans()]) }}</span>
    @else
        <x-ui::icon name="alert-triangle" :size="14" class="text-danger" />
        <span class="text-danger">{{ $last->message() }}@if ($last->detail) — <bdi>{{ $last->detail }}</bdi>@endif</span>
    @endif
</p>
