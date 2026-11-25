<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Enums\StaticTextSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One storefront static text of the current store in one locale (tenant DB).
 *
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property string $value
 * @property string|null $source_hash
 * @property StaticTextSource $source
 * @property bool $is_locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantStaticText extends Model
{
    protected $table = 'tenant_static_texts';

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'locale', 'value', 'source_hash', 'source', 'is_locked'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => StaticTextSource::class,
            'is_locked' => 'boolean',
        ];
    }

    /**
     * Manually edited or locked rows are never overwritten by sync or AI runs.
     */
    public function isProtected(): bool
    {
        return $this->is_locked || $this->source->isManual();
    }
}
