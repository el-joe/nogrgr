{{-- Shipping timeline: tracking events (newest first) and the order status history, one row per status and minute. --}}
@php
    $entries = collect();
    foreach ($order->trackingEvents as $event) {
        $entries->push(['label' => $event->status->label().($event->location ? ' — '.$event->location : ''), 'meta' => $event->note, 'at' => $event->created_at, 'tone' => 'primary']);
    }
    foreach ($order->history as $row) {
        $entries->push(['label' => $row->to_status->label(), 'meta' => $row->note, 'at' => $row->created_at, 'tone' => 'success']);
    }
    // RQA-VEN-010: the order status ("Delivered") and the shipment status ("Delivered") are
    // recorded at the same moment; list a status once per minute (the shown precision).
    $entries = $entries->sortByDesc(fn ($entry) => $entry['at']?->getTimestamp() ?? 0)
        ->unique(fn ($entry) => mb_strtolower($entry['label']).'|'.($entry['at']?->format('Y-m-d H:i') ?? ''))
        ->values();
@endphp
@if ($entries->isEmpty())
    <x-ui::empty-state compact :title="__('commerce::orders_admin.timeline.empty')" icon="clock" />
@else
    <ol class="relative grid gap-4 ps-5">
        <span class="absolute inset-y-1 start-[5px] w-px bg-line" aria-hidden="true"></span>
        @foreach ($entries as $entry)
            <li class="relative">
                <span @class(['absolute -start-5 top-1 size-[11px] rounded-full ring-2 ring-surface', 'bg-primary' => $loop->first, 'bg-success' => ! $loop->first])></span>
                <p class="text-sm font-medium text-ink">{{ $entry['label'] }}</p>
                <p class="text-xs text-ink-secondary">{{ $entry['at']?->translatedFormat('j M, H:i') }}@if ($entry['meta']) · {{ $entry['meta'] }}@endif</p>
            </li>
        @endforeach
    </ol>
@endif
