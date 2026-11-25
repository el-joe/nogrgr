<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\VendorStaffFactory;
use App\Modules\Identity\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Store staff member (tenant DB, guard "vendor").
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
class VendorStaff extends Authenticatable
{
    /** @use HasFactory<VendorStaffFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    protected $table = 'vendor_staff';

    protected string $guard_name = 'vendor';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'password', 'status', 'locale', 'is_owner'];

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
            'is_owner' => 'boolean',
        ];
    }
}
