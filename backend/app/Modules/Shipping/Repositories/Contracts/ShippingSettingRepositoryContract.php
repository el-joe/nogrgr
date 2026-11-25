<?php

namespace App\Modules\Shipping\Repositories\Contracts;

use App\Modules\Shipping\Models\ShippingSetting;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Key → JSON value shipping settings (ADM-034).
 *
 * @extends RepositoryContract<ShippingSetting>
 */
interface ShippingSettingRepositoryContract extends RepositoryContract
{
    /**
     * @return array<string, mixed>
     */
    public function values(): array;

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void;
}
