<?php

namespace App\Modules\Platform\Models;

use App\Modules\Platform\Concerns\Auditable;
use App\Modules\Platform\Database\Factories\ComplianceDocumentFactory;
use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A KYC document (central DB). The file sits on a private disk and is only served through
 * the authorized admin controller; the document number is encrypted at rest.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $compliance_profile_id
 * @property ComplianceDocumentType $type
 * @property ComplianceDocumentStatus $status
 * @property string|null $document_number
 * @property string|null $holder_name
 * @property Carbon|null $expires_at
 * @property bool|null $matches_owner
 * @property string $disk
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ComplianceDocument extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<ComplianceDocumentFactory> */
    use HasFactory;

    protected $table = 'compliance_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'compliance_profile_id', 'type', 'status', 'document_number', 'holder_name', 'expires_at',
        'matches_owner', 'disk', 'path', 'original_name', 'mime_type', 'size',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['document_number'];

    protected static function newFactory(): ComplianceDocumentFactory
    {
        return ComplianceDocumentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ComplianceDocumentType::class,
            'status' => ComplianceDocumentStatus::class,
            'document_number' => 'encrypted',
            'expires_at' => 'date',
            'matches_owner' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ComplianceProfile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ComplianceProfile::class, 'compliance_profile_id')->withoutGlobalScopes();
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * "1••••••42" — first and last two characters only.
     */
    public function maskedNumber(): ?string
    {
        $number = $this->document_number;

        if ($number === null || $number === '') {
            return null;
        }

        $length = mb_strlen($number);

        return $length <= 4 ? str_repeat('•', $length) : mb_substr($number, 0, 1).str_repeat('•', $length - 3).mb_substr($number, -2);
    }
}
