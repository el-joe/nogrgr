{{-- Details card: status / priority selects (product requests, managers) + request facts. --}}
@use('App\Modules\Catalog\Enums\RequestKind')
@use('App\Modules\Catalog\Enums\RequestPriority')
@php
    $locale = app()->getLocale();
    $rows = [[__('catalog::requests.details.vendor'), $store?->name ?? '—']];

    if ($kind === RequestKind::Product) {
        $rows[] = [__('catalog::requests.details.category'), $request->category?->name ?? __('catalog::requests.details.none')];
        $rows[] = [__('catalog::requests.details.target_price'), $request->target_price?->format() ?? __('catalog::requests.details.none')];
    }

    if ($kind === RequestKind::Manufacturing) {
        $rows[] = [__('catalog::requests.details.quantity'), number_format((int) $request->quantity)];
        $rows[] = [__('catalog::requests.details.product'), $request->product?->name ?? __('catalog::requests.details.none')];
    }

    if ($request->submitted_by) {
        $rows[] = [__('catalog::requests.details.submitted_by'), $request->submitted_by];
    }

    $rows[] = [__('catalog::requests.details.created'), $request->created_at?->locale($locale)->translatedFormat('j M, H:i')];
    $link = match ($kind) {
        RequestKind::Product => $request->product_url,
        RequestKind::Manufacturing => $request->reference_url,
        RequestKind::Brand => $request->website,
    };
@endphp
<x-ui::card :title="__('catalog::requests.details.title')" id="request-details">
    @if ($kind === RequestKind::Product && $canManage)
        <div class="mb-4 grid gap-4">
            <x-ui::select name="status" :label="__('catalog::requests.details.status')" :options="$statuses" :selected="$request->status->value" :search="false" data-request-field :data-url="route('admin.requests.products.status', $request->id)" />
            <x-ui::select name="priority" :label="__('catalog::requests.details.priority')" :options="RequestPriority::options()" :selected="$request->priority->value" :search="false" data-request-field :data-url="route('admin.requests.products.priority', $request->id)" />
        </div>
    @elseif ($kind === RequestKind::Product)
        <dl class="mb-3 grid gap-3 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('catalog::requests.details.priority') }}</dt><dd><x-ui::badge :tone="$request->priority->tone()">{{ $request->priority->label() }}</x-ui::badge></dd></div>
        </dl>
    @endif
    <dl class="grid gap-3 text-sm">
        @foreach ($rows as [$label, $value])
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ $label }}</dt>
                <dd class="text-end font-medium text-ink">{{ $value }}</dd>
            </div>
        @endforeach
        @if ($link)
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ $kind === RequestKind::Brand ? __('catalog::requests.details.website') : __('catalog::requests.details.reference_url') }}</dt>
                <dd class="min-w-0 text-end"><a href="{{ $link }}" target="_blank" rel="noopener nofollow" class="block truncate font-medium text-ink-brand hover:underline"><bdi dir="ltr">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $link), 40) }}</bdi></a></dd>
            </div>
        @endif
    </dl>
</x-ui::card>
