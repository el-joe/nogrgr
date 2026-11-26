<?php

namespace App\Modules\Ai\Models;

use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Enums\TranslationItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * State of one item of the paid store translation (tenant DB).
 *
 * @property int $id
 * @property int|null $run_id central store_translation_runs.id
 * @property StoreTranslationSection $section
 * @property string $item_key
 * @property string $locale
 * @property TranslationItemStatus $status
 * @property string|null $source_hash
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $translated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreTranslationItem extends Model
{
    protected $table = 'store_translation_items';

    /**
     * @var list<string>
     */
    protected $fillable = ['run_id', 'section', 'item_key', 'locale', 'status', 'source_hash', 'attempts', 'error', 'translated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'section' => StoreTranslationSection::class,
            'status' => TranslationItemStatus::class,
            'attempts' => 'integer',
            'translated_at' => 'datetime',
        ];
    }
}
