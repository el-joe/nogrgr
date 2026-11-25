<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Tenant DB (current store).
 *
 * @extends RepositoryContract<StoreSetting>
 */
interface StoreSettingRepositoryContract extends RepositoryContract
{
    public function get(StoreSettingKey $key): mixed;

    public function put(StoreSettingKey $key, mixed $value): void;

    public function forget(StoreSettingKey $key): void;
}
