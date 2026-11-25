{{--
    "Preview" step inside the Run import modal (fetched as JSON html): totals of the
    source and the first products as they will be imported.
    @param array{total: int, pages: int, invalid: int, items: list<array<string, mixed>>} $preview
--}}
<div class="grid gap-3">
    <p class="text-sm font-medium text-ink">{{ __('catalog::imports.modal.preview_summary', ['total' => number_format($preview['total']), 'pages' => number_format($preview['pages'])]) }}</p>
    @if ($preview['invalid'] > 0)
        <p class="rounded-control bg-warning-bg px-3 py-2 text-[13px] text-warning">{{ trans_choice('catalog::imports.modal.preview_invalid', $preview['invalid'], ['count' => $preview['invalid']]) }}</p>
    @endif
    @if ($preview['items'] === [])
        <x-ui::empty-state compact :title="__('catalog::imports.modal.preview_empty')" description="" />
    @else
        <div class="relative overflow-x-auto rounded-control border border-line">
            <table class="ui-table w-full text-[13px]">
                <thead>
                    <tr class="border-b border-line bg-subtle text-xs font-medium text-ink-secondary">
                        <th scope="col" class="px-3 py-2 text-start">{{ __('catalog::imports.modal.preview_columns.product') }}</th>
                        <th scope="col" class="px-3 py-2 text-end">{{ __('catalog::imports.modal.preview_columns.price') }}</th>
                        <th scope="col" class="px-3 py-2 text-end">{{ __('catalog::imports.modal.preview_columns.variants') }}</th>
                        <th scope="col" class="px-3 py-2 text-end">{{ __('catalog::imports.modal.preview_columns.images') }}</th>
                        <th scope="col" class="px-3 py-2 text-start">{{ __('catalog::imports.modal.preview_columns.categories') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview['items'] as $item)
                        <tr class="border-b border-line last:border-b-0">
                            <td class="px-3 py-2">
                                <span class="block font-medium text-ink">{{ $item['name'] }}</span>
                                <bdi dir="ltr" class="text-xs text-ink-secondary">{{ $item['sku'] }}</bdi>
                            </td>
                            <td class="px-3 py-2 text-end"><bdi dir="ltr" class="tabular-nums">{{ $item['price'] }}</bdi></td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $item['variants'] }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $item['images'] }}</td>
                            <td class="px-3 py-2 text-ink-secondary">{{ $item['categories'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
