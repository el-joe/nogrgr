<?php

use App\Shared\Routing\VendorPanel;
use App\Shared\Routing\VendorPanelMode;

if (! function_exists('vendor_route')) {
    /**
     * URL of a vendor panel route that works on the store host and the central host.
     *
     *   vendor_route('dashboard')                 // {store}/admin/dashboard | {central}/tenant/{slug}/dashboard
     *   vendor_route('products.edit', $product)
     *
     * @param  array<string, mixed>|mixed  $parameters
     */
    function vendor_route(string $name, mixed $parameters = [], bool $absolute = true, ?VendorPanelMode $mode = null): string
    {
        return VendorPanel::route($name, is_array($parameters) ? $parameters : [$parameters], $absolute, $mode);
    }
}
