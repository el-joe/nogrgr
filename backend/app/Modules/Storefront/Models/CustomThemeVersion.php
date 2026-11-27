<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One uploaded package of a store's custom theme. `report` = the validator output
 * (errors, warnings, files with sha256 / size, templates); `manifest` = the parsed
 * theme.json (null when the package failed before it could be read).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $custom_theme_id
 * @property string $version
 * @property CustomThemeVersionStatus $status
 * @property string $name
 * @property array<string, mixed>|null $manifest
 * @property array<string, mixed> $report
 * @property string $checksum
 * @property int $size_bytes
 * @property int $file_count
 * @property string|null $original_filename
 * @property int|null $uploaded_by_id
 * @property string|null $uploaded_by_name
 * @property string|null $submit_note
 * @property Carbon|null $submitted_at
 * @property int|null $reviewed_by_id
 * @property string|null $reviewed_by_name
 * @property string|null $review_note
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomTheme $theme
 */
class CustomThemeVersion extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'custom_theme_versions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'custom_theme_id', 'version', 'status', 'name', 'manifest', 'report', 'checksum', 'size_bytes', 'file_count',
        'original_filename', 'uploaded_by_id', 'uploaded_by_name', 'submit_note', 'submitted_at',
        'reviewed_by_id', 'reviewed_by_name', 'review_note', 'reviewed_at', 'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomThemeVersionStatus::class,
            'manifest' => 'array',
            'report' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CustomTheme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(CustomTheme::class, 'custom_theme_id');
    }

    /**
     * Folder of the extracted package on the `custom-themes` disk.
     */
    public function directory(): string
    {
        return $this->tenant_id.'/'.$this->id;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return array_values(array_map('strval', (array) ($this->report['errors'] ?? [])));
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return array_values(array_map('strval', (array) ($this->report['warnings'] ?? [])));
    }

    /**
     * @return array<string, array{size: int, sha256: string}>
     */
    public function files(): array
    {
        $files = $this->report['files'] ?? [];

        return is_array($files) ? $files : [];
    }
}
