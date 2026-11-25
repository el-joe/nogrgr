{{-- Toast region (top-end on desktop, top-center on mobile) with session flashes: status|success|error|warning|info. --}}
@php
    $session = request()->hasSession() ? request()->session() : null;
    $flashes = collect(['success' => $session?->get('success') ?? $session?->get('status'), 'error' => $session?->get('error'), 'warning' => $session?->get('warning'), 'info' => $session?->get('info')])->filter();
@endphp
<div id="ui-toasts" class="ui-toasts" aria-live="polite" aria-relevant="additions">
    @foreach ($flashes as $tone => $message)
        <x-ui::toast :tone="$tone" :title="$message" />
    @endforeach
</div>
