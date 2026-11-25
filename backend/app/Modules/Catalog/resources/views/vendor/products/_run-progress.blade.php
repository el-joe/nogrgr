{{-- Queued markup run progress (VEN-010a): filled by catalog.js while polling the run. --}}
<div data-run-progress hidden class="grid gap-2 rounded-control bg-subtle p-3" role="status" aria-live="polite">
    <div class="flex items-center justify-between text-sm">
        <span class="font-medium text-ink" data-run-label>{{ __('catalog::enums.price_list_run_status.queued') }}</span>
        <span class="text-ink-secondary" dir="ltr" data-run-count></span>
    </div>
    <div class="vnd-run-bar"><span data-run-bar class="w-0"></span></div>
</div>
