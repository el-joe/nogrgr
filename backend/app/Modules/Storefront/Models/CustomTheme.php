<?php

namespace App\Modules\Storefront\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A store's own Twig theme (VEN-036, decision #121): one per store, with uploaded
 * versions. `is_active` = the storefront renders the live version (else the store's
 * built-in theme); `settings` = the vendor's values for the live manifest's schema.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int|null $live_version_id
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $last_render_error_at
 * @property string|null $last_render_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomThemeVersion|null $liveVersion
 */
class CustomTheme extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'custom_themes';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'name', 'live_version_id', 'is_active', 'settings', 'last_render_error_at', 'last_render_error'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'last_render_error_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CustomThemeVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CustomThemeVersion::class, 'custom_theme_id');
    }

    /**
     * @return BelongsTo<CustomThemeVersion, $this>
     */
    public function liveVersion(): BelongsTo
    {
        return $this->belongsTo(CustomThemeVersion::class, 'live_version_id');
    }
}
