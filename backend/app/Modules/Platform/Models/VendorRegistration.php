<?php

namespace App\Modules\Platform\Models;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Concerns\Auditable;
use App\Modules\Platform\Database\Factories\VendorRegistrationFactory;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A central-website sign-up (central DB). tenant_id is set once the store is provisioned.
 *
 * @property int $id
 * @property string $email
 * @property string|null $phone
 * @property string|null $locale
 * @property int|null $plan_id
 * @property BillingTerm|null $term
 * @property RegistrationStatus $status
 * @property string|null $owner_name
 * @property string|null $password
 * @property string|null $store_name
 * @property string|null $slug
 * @property string|null $custom_domain
 * @property int|null $profit_percent_bps
 * @property string|null $token_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $link_sent_at
 * @property int $link_sent_count
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $completed_at
 * @property int|null $tenant_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan|null $plan
 */
class VendorRegistration extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<VendorRegistrationFactory> */
    use HasFactory;

    protected $table = 'vendor_registrations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email', 'phone', 'locale', 'plan_id', 'term', 'status', 'owner_name', 'password', 'store_name', 'slug',
        'custom_domain', 'profit_percent_bps', 'token_hash', 'expires_at', 'link_sent_at', 'link_sent_count',
        'email_verified_at', 'completed_at', 'tenant_id', 'meta',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'token_hash'];

    protected static function newFactory(): VendorRegistrationFactory
    {
        return VendorRegistrationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => BillingTerm::class,
            'status' => RegistrationStatus::class,
            'password' => 'hashed',
            'profit_percent_bps' => 'integer',
            'link_sent_count' => 'integer',
            'expires_at' => 'datetime',
            'link_sent_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Display name in lists: owner name, else the e-mail's local part.
     */
    public function applicantName(): string
    {
        return $this->owner_name ?? (string) strstr($this->email, '@', true);
    }
}
