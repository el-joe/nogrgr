{{-- Status pill of an AI video ad (client State set 12:40366); generating states show the live percentage. --}}
@php
    $tone = match (true) {
        $ad->status === \App\Modules\Ai\Enums\VideoAdStatus::Ready => 'success',
        $ad->status === \App\Modules\Ai\Enums\VideoAdStatus::Failed => 'danger',
        default => 'brand',
    };
@endphp
<span class="inline-grid gap-1" data-ad-row-status="{{ $ad->status->value }}" @if ($ad->status->isActive()) data-ad-active="{{ $ad->id }}" @endif>
    <x-ui::badge :tone="$tone" dot>{{ $ad->status->label() }}@if ($ad->status->isActive()) <bdi dir="ltr">{{ $ad->progress }}%</bdi>@endif</x-ui::badge>
    @if ($ad->status === \App\Modules\Ai\Enums\VideoAdStatus::Failed && $ad->error)
        <span class="max-w-[220px] text-xs text-ink-muted">{{ $ad->error }}</span>
    @endif
</span>
