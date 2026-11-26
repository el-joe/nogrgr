<?php

namespace App\Modules\Localization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A store content field + locale translated by hand (tenant DB); the AI run never
 * overwrites it (decision #44).
 *
 * @property int $id
 * @property string $content_type
 * @property int $content_id
 * @property string $field
 * @property string $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContentTranslationLock extends Model
{
    protected $table = 'content_translation_locks';

    /**
     * @var list<string>
     */
    protected $fillable = ['content_type', 'content_id', 'field', 'locale'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content_id' => 'integer'];
    }
}
