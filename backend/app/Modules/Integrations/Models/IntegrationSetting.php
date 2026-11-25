<?php

namespace App\Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One platform setting (central). `value` is the raw stored text: JSON, encrypted
 * with the app key when `is_secret`. Encode/decode only through SettingsRepository.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property bool $is_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IntegrationSetting extends Model
{
    use CentralConnection;

    protected $table = 'integration_settings';

    /**
     * @var list<string>
     */
    protected $fillable = ['group', 'key', 'value', 'is_secret'];

    /**
     * @var list<string>
     */
    protected $hidden = ['value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
