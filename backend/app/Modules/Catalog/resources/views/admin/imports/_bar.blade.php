{{--
    Labelled progress bar; the width is set from data-percent by catalog-import.js.
    @param string $key  products | images, string $label, array{done: int, total: int, percent: int} $value
--}}
<div class="grid gap-2">
    <div class="flex items-center justify-between gap-3 text-sm">
        <span class="font-medium text-ink">{{ $label }}</span>
        <bdi dir="ltr" class="tabular-nums text-xs text-ink-secondary" data-progress-text="{{ $key }}">{{ number_format($value['done']) }} / {{ number_format($value['total']) }}</bdi>
    </div>
    <div class="h-2 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value['percent'] }}" data-progress-track="{{ $key }}">
        <div class="h-full w-0 rounded-full bg-primary transition-[width] duration-500 ease-out motion-reduce:transition-none" data-progress-bar="{{ $key }}" data-percent="{{ $value['percent'] }}"></div>
    </div>
</div>
