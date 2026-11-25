@if ($product->trashed())
    <x-ui::status status="cancelled">{{ __('catalog::products.trashed') }}</x-ui::status>
@elseif ($product->manage_stock && $product->stock <= 0 && $product->status === \App\Modules\Catalog\Enums\ProductStatus::Published)
    <x-ui::status status="out_of_stock">{{ __('catalog::products.stock_states.out_of_stock') }}</x-ui::status>
@else
    <x-ui::status :status="$product->status === \App\Modules\Catalog\Enums\ProductStatus::Published ? 'active' : ($product->status === \App\Modules\Catalog\Enums\ProductStatus::Draft ? 'draft' : 'inactive')">{{ $product->status->label() }}</x-ui::status>
@endif
