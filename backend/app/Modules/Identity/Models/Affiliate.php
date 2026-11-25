<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Concerns\SendsPasswordResetLink;
use App\Modules\Identity\Database\Factories\AffiliateFactory;
use App\Modules\Identity\Enums\AccountStatus;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Affiliate partner (central DB, guard "affiliate"). Every tenant owns one (tenant_id).
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property AccountStatus $status
 * @property string|null $locale
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Affiliate extends Authenticatable implements HasLocalePreference
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<AffiliateFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SendsPasswordResetLink;

    protected $table = 'affiliates';

    protected string $guard_name = 'affiliate';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'password', 'status', 'locale', 'tenant_id'];

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
        ];
    }

    protected function passwordResetRoute(): string
    {
        return 'affiliate.password.reset';
    }

    protected function passwordBroker(): string
    {
        return 'affiliates';
    }
}
