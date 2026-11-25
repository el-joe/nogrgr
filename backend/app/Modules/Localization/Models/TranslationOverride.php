<?php

namespace App\Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * An admin's value for one translation key in one locale (central DB, ADM-092).
 *
 * @property int $id
 * @property string $file
 * @property string $key
 * @property string $locale
 * @property string $value
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TranslationOverride extends Model
{
    use CentralConnection;

    protected $table = 'translation_overrides';

    /**
     * @var list<string>
     */
    protected $fillable = ['file', 'key', 'locale', 'value', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['updated_by' => 'integer'];
    }
}
