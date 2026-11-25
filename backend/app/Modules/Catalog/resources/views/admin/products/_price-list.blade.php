{{--
    Price list modal body (ADM-010b, Figma Admin Panel 65:29682): one row per allowed
    country × active variant. Shopper = base + fixed shipping (Commerce\Pricing).
--}}
<div class="grid gap-4" data-modal-title="{{ __('catalog::products.prices.title', ['name' => $product->name]) }}" data-modal-subtitle="{{ __('catalog::products.prices.subtitle') }}">
    @if ($rows === [])
        <x-ui::empty-state compact icon="globe" :description="__('catalog::products.prices.empty')" />
    @else
        <div class="max-h-[60vh] overflow-auto rounded-control border border-line">
            <table class="w-full text-start text-sm">
                <thead class="sticky top-0 bg-subtle text-xs text-ink-secondary">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('catalog::products.prices.country') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('catalog::products.prices.variant') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-end font-medium">{{ __('catalog::products.prices.base') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-end font-medium">{{ __('catalog::products.prices.shipping') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-end font-medium">{{ __('catalog::products.prices.shopper') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-ink">{{ trim($row['country']->flag.' '.$row['country']->name) }}</td>
                            <td class="px-4 py-3 text-ink-secondary">{{ $row['variant'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-end font-medium text-ink" dir="ltr">{{ $row['base']->format() }}</td>
                            <td class="px-4 py-3 text-end text-ink-secondary" dir="ltr">{{ $row['shipping']->format() }}</td>
                            <td class="px-4 py-3 text-end font-semibold text-ink" dir="ltr">{{ $row['shopper']->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (auth('admin')->user()?->can('catalog.products.export'))
            <div class="flex justify-end">
                <x-ui::button size="sm" icon="download" :href="route('admin.catalog.products.prices.export', $product)">{{ __('catalog::products.prices.export') }}</x-ui::button>
            </div>
        @endif
    @endif
</div>
