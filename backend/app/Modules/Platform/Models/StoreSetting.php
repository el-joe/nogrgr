<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Enums\StoreSettingKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One setting of the current store (tenant DB).
 *
 * @property int $id
 * @property StoreSettingKey $key
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreSetting extends Model
{
    protected $table = 'store_settings';

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => StoreSettingKey::class,
            'value' => 'json',
        ];
    }
}
