<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Concerns\SendsPasswordResetLink;
use App\Modules\Identity\Database\Factories\CustomerFactory;
use App\Modules\Identity\Enums\AccountStatus;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Store customer (tenant DB, guard "customer" on web, Sanctum tokens on mobile).
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $phone_country
 * @property string|null $country_code
 * @property array<string, array<string, bool>>|null $notification_preferences
 * @property string|null $password
 * @property AccountStatus $status
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Customer extends Authenticatable implements HasLocalePreference
{
    use HasApiTokens;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use Notifiable;
    use SendsPasswordResetLink;
    use SoftDeletes;

    protected $table = 'customers';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'phone_country', 'password', 'status', 'locale', 'country_code', 'notification_preferences'];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

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
            'phone_verified_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    protected function passwordResetRoute(): string
    {
        return 'storefront.account.password.reset';
    }

    protected function passwordBroker(): string
    {
        return 'customers';
    }
}
