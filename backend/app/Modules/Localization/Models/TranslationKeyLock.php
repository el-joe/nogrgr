<?php

namespace App\Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A translation key locked by an admin (central DB, ADM-093).
 *
 * @property int $id
 * @property string $file
 * @property string $key
 * @property int|null $locked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TranslationKeyLock extends Model
{
    use CentralConnection;

    protected $table = 'translation_key_locks';

    /**
     * @var list<string>
     */
    protected $fillable = ['file', 'key', 'locked_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['locked_by' => 'integer'];
    }
}
