<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Storefront\Enums\ThemePartType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Template part of a catalogue theme (ADM-088 / 088a, central): a named set of settings
 * for one part type (ThemePartType declares the fields). One default per theme + type;
 * the default part is applied on the storefront while it is active.
 *
 * @property int $id
 * @property int $theme_id
 * @property ThemePartType $type
 * @property string $name
 * @property array<string, mixed>|null $settings
 * @property string|null $preview_path
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 * @property-read string|null $preview_url
 * @property-read Theme $theme
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ThemePart extends Model
{
    use CentralConnection;

    protected $table = 'theme_parts';

    /**
     * @var list<string>
     */
    protected $fillable = ['theme_id', 'type', 'name', 'settings', 'preview_path', 'is_active', 'is_default', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ThemePartType::class,
            'settings' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    /**
     * A setting value; translatable text fields hold {locale: text} and return the
     * current locale's text (then English, then the first one).
     */
    public function setting(string $name, mixed $default = null): mixed
    {
        $value = ($this->settings ?? [])[$name] ?? null;

        if (is_array($value)) {
            $text = $value[app()->getLocale()] ?? $value['en'] ?? (array_values(array_filter($value, static fn ($item): bool => is_string($item) && $item !== ''))[0] ?? null);

            return is_string($text) && $text !== '' ? $text : $default;
        }

        return $value ?? $default;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->preview_path !== null ? Storage::disk('public')->url($this->preview_path) : null);
    }
}
