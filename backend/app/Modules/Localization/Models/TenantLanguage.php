<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Database\Factories\TenantLanguageFactory;
use App\Modules\Localization\Enums\TenantLanguageSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A language switched on for the current store (tenant DB). `language_code` points to
 * central `languages.code` (different databases, no FK).
 *
 * @property int $id
 * @property string $language_code
 * @property bool $is_enabled
 * @property bool $is_default
 * @property int $sort_order
 * @property TenantLanguageSource $source
 * @property Carbon|null $enabled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantLanguage extends Model
{
    /** @use HasFactory<TenantLanguageFactory> */
    use HasFactory;

    protected $table = 'tenant_languages';

    /**
     * @var list<string>
     */
    protected $fillable = ['language_code', 'is_enabled', 'is_default', 'sort_order', 'source', 'enabled_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'source' => TenantLanguageSource::class,
            'enabled_at' => 'datetime',
        ];
    }

    public function isCore(): bool
    {
        return in_array($this->language_code, Language::CORE, true);
    }
}
