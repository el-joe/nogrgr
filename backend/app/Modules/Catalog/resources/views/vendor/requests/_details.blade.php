{{-- Request facts card (status, priority for product requests, created, kind details). --}}
@use('App\Modules\Catalog\Enums\RequestKind')
@php
    $locale = app()->getLocale();
    $countryIds = (array) ($request->target_country_ids ?? []);
    $countryNames = $countryIds === [] ? [] : app(\App\Modules\Localization\Services\CountryService::class)->active(array_map('intval', $countryIds))->map(fn ($country) => (string) $country->name)->all();
    $rows = [];
    $rows[__('catalog::vendor_requests.details.status')] = null;
    if ($kind === RequestKind::Product) {
        $rows[__('catalog::vendor_requests.details.priority')] = $request->priority->label();
    }
    if ($kind === RequestKind::Manufacturing) {
        $rows[__('catalog::vendor_requests.details.quantity')] = number_format($request->quantity);
        if ($request->product) {
            $rows[__('catalog::vendor_requests.details.product')] = (string) $request->product->name;
        }
    }
    if ($kind === RequestKind::Brand && $request->category) {
        $rows[__('catalog::vendor_requests.details.category')] = (string) $request->category->name;
    }
    if ($countryNames !== []) {
        $rows[__('catalog::vendor_requests.details.markets')] = implode($locale === 'ar' ? '، ' : ', ', $countryNames);
    }
    $rows[__('catalog::vendor_requests.details.created')] = $request->created_at?->locale($locale)->translatedFormat('j M H:i');
    $link = $kind === RequestKind::Product ? $request->product_url : ($kind === RequestKind::Brand ? $request->website : null);
@endphp
<x-ui::card :title="__('catalog::vendor_requests.details.title')" id="request-details">
    <dl class="grid gap-3 text-sm">
        @foreach ($rows as $label => $value)
            <div class="flex items-start justify-between gap-3">
                <dt class="text-ink-secondary">{{ $label }}</dt>
                <dd class="text-end font-medium text-ink">
                    @if ($value === null)
                        <x-ui::badge :tone="$request->statusTone()" dot>{{ $request->statusLabel() }}</x-ui::badge>
                    @else
                        {{ $value }}
                    @endif
                </dd>
            </div>
        @endforeach
        @if (filled($link))
            <div class="flex items-start justify-between gap-3">
                <dt class="text-ink-secondary">{{ __('catalog::vendor_requests.details.link') }}</dt>
                <dd class="min-w-0 text-end"><a href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow" class="block truncate font-medium text-ink-brand hover:underline" dir="ltr">{{ \Illuminate\Support\Str::limit($link, 40) }}</a></dd>
            </div>
        @endif
    </dl>
</x-ui::card>
