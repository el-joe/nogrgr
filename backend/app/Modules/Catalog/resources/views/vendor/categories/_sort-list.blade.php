{{--
    Drag & drop product list (VEN-017a order tab, VEN-018b badge order) — same rows as the
    product sort (VEN-014). The header "Save order" button ([data-sort-save]) posts the ids.
    Data: $rows (StoreTaxonomyPageService::sortRows()), $sortUrl, $canManage, $title, $hint,
    optional $note (row subtitle instead of the price), $removeUrl (fn (int $id): string),
    $removeLabel, $removeText and $removeTitle (fn (string $name): string).
--}}
<section class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card" data-product-sort data-sort-url="{{ $sortUrl }}" @if (! $canManage) data-readonly @endif>
    <div class="grid gap-0.5">
        <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
        @isset($hint)
            <p class="text-xs text-ink-secondary">{{ $hint }}</p>
        @endisset
    </div>
    <p class="text-xs text-ink-muted" data-sort-dirty hidden>{{ __('catalog::vendor.sort.unsaved') }}</p>
    <ol class="grid gap-2" data-sort-list>
        @foreach ($rows as $index => $row)
            @php($view = $row['view'])
            <li class="vnd-sort-item flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2" data-sort-item data-id="{{ $row['id'] }}" tabindex="0" aria-label="{{ $view->name }}">
                <button type="button" class="cursor-grab text-ink-muted" data-drag-handle aria-label="{{ __('catalog::vendor.sort.drag', ['name' => $view->name]) }}" @disabled(! $canManage)><x-ui::icon name="menu" :size="18" /></button>
                <span class="w-6 text-xs text-ink-muted" data-sort-position dir="ltr">{{ $index + 1 }}</span>
                @if ($view->primaryImageUrl())
                    <img src="{{ $view->primaryImageUrl() }}" alt="" class="size-9 rounded-chip object-cover" loading="lazy">
                @else
                    <span class="grid size-9 place-items-center rounded-chip bg-primary-subtle text-ink-brand"><x-ui::icon name="box" :size="16" /></span>
                @endif
                <span class="grid min-w-0 flex-1">
                    <span class="truncate text-sm font-medium text-ink">{{ $view->name }}</span>
                    <span class="text-xs text-ink-secondary">@isset($note){{ $note }}@else<bdi dir="ltr">{{ $row['price'] }}</bdi>@endisset</span>
                </span>
                @if ($view->isOwn())
                    <x-ui::badge tone="info">{{ __('catalog::enums.store_product_source.own') }}</x-ui::badge>
                @endif
                @if ($canManage)
                    <span class="flex gap-1">
                        <x-ui::icon-button icon="chevron-up" size="sm" :label="__('catalog::vendor.sort.up')" data-sort-move="up" />
                        <x-ui::icon-button icon="chevron-down" size="sm" :label="__('catalog::vendor.sort.down')" data-sort-move="down" />
                        @isset($removeUrl)
                            <x-ui::icon-button icon="trash" size="sm" :label="$removeLabel" :data-confirm="$removeText" :data-confirm-title="$removeTitle($view->name)" data-confirm-method="DELETE" :data-confirm-url="$removeUrl($row['id'])" />
                        @endisset
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</section>
