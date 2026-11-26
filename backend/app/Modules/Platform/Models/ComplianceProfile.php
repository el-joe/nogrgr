<?php

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Concerns\Auditable;
use App\Modules\Platform\Database\Factories\ComplianceProfileFactory;
use App\Modules\Platform\Enums\ComplianceAccountType;
use App\Modules\Platform\Enums\ComplianceRisk;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Store KYC profile (central DB, one per tenant). IBAN is encrypted at rest.
 *
 * @property int $id
 * @property int $tenant_id
 * @property ComplianceStatus $status
 * @property ComplianceRisk $risk
 * @property ComplianceAccountType|null $account_type
 * @property string|null $owner_name
 * @property string|null $owner_id_number
 * @property Carbon|null $owner_id_expires_at
 * @property Carbon|null $owner_birth_date
 * @property string|null $owner_phone
 * @property string|null $legal_name
 * @property string|null $registration_number
 * @property Carbon|null $registration_expires_at
 * @property string|null $vat_number
 * @property int|null $country_id
 * @property int|null $city_id
 * @property string|null $address
 * @property string|null $company_phone
 * @property string|null $company_email
 * @property string|null $bank_name
 * @property string|null $iban
 * @property string|null $account_name
 * @property string|null $terms_version
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_accepted_ip
 * @property string|null $decision_note
 * @property int|null $reviewed_by_admin_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, ComplianceDocument> $documents
 */
class ComplianceProfile extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<ComplianceProfileFactory> */
    use HasFactory;

    protected $table = 'compliance_profiles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'status', 'risk', 'account_type', 'owner_name', 'owner_id_number', 'owner_id_expires_at', 'owner_birth_date',
        'owner_phone', 'legal_name', 'registration_number', 'registration_expires_at', 'vat_number', 'country_id', 'city_id',
        'address', 'company_phone', 'company_email', 'bank_name', 'iban', 'account_name', 'terms_version',
        'terms_accepted_at', 'terms_accepted_ip', 'decision_note', 'reviewed_by_admin_id', 'reviewed_at', 'submitted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['iban', 'owner_id_number'];

    protected static function newFactory(): ComplianceProfileFactory
    {
        return ComplianceProfileFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplianceStatus::class,
            'risk' => ComplianceRisk::class,
            'account_type' => ComplianceAccountType::class,
            'owner_id_number' => 'encrypted',
            'owner_id_expires_at' => 'date',
            'owner_birth_date' => 'date',
            'registration_expires_at' => 'date',
            'iban' => 'encrypted',
            'terms_accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ComplianceDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ComplianceDocument::class)->withoutGlobalScopes()->orderBy('id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    /**
     * "SA03 8000 0000 ••• 1188": country + bank code, then the last four only.
     */
    public function maskedIban(): ?string
    {
        if ($this->iban === null || $this->iban === '') {
            return null;
        }

        $iban = Str::upper(preg_replace('/\s+/', '', $this->iban) ?? '');

        return mb_strlen($iban) <= 8 ? $iban : trim(chunk_split(mb_substr($iban, 0, 8), 4, ' ')).' ••• '.mb_substr($iban, -4);
    }
}
