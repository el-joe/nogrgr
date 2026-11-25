{{--
    Connection status badge, all states pre-rendered so integrations.js can switch it
    after a test without rebuilding markup.
    @param string $key  status slot id (section or provider)
    @param \App\Modules\Integrations\Enums\ConnectionStatus $status
    @param \App\Modules\Integrations\DTOs\ConnectionTestResult|null $last
--}}
<span class="inline-flex flex-col items-end gap-1" data-status-badge="{{ $key }}">
    @foreach (\App\Modules\Integrations\Enums\ConnectionStatus::cases() as $case)
        <x-ui::badge :tone="$case->tone()" data-status-value="{{ $case->value }}" :hidden="$case !== $status">{{ $case->label() }}</x-ui::badge>
    @endforeach
</span>
