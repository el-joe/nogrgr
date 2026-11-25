<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Concerns\SendsPasswordResetLink;
use App\Modules\Identity\Database\Factories\AdminFactory;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Platform\Concerns\Auditable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform administrator (central DB, guard "admin").
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
class Admin extends Authenticatable implements HasLocalePreference
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SendsPasswordResetLink;

    protected $table = 'admins';

    protected string $guard_name = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'password', 'status', 'locale'];

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
        return 'admin.password.reset';
    }

    protected function passwordBroker(): string
    {
        return 'admins';
    }
}
