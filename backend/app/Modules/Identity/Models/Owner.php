<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\OwnerFactory;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Store owner (central DB, guard "owner"): one person, many stores. Signs in through
 * the central store login (password of any owned store) or Google / Apple. The owner
 * of each store also exists as a VendorStaff (role owner) inside the store's database.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $apple_id
 * @property AccountStatus $status
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Tenant> $tenants
 */
class Owner extends Authenticatable implements HasLocalePreference
{
    use CentralConnection;

    /** @use HasFactory<OwnerFactory> */
    use HasFactory;

    use Notifiable;

    protected $table = 'owners';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'password', 'google_id', 'apple_id', 'status', 'locale', 'email_verified_at', 'last_login_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token', 'google_id', 'apple_id'];

    protected static function newFactory(): OwnerFactory
    {
        return OwnerFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice(array_filter($words), 0, 2))));
    }
}
