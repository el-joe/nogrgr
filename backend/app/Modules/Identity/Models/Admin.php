<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Concerns\SendsPasswordResetLink;
use App\Modules\Identity\Database\Factories\AdminFactory;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Notifications\Concerns\ReceivesPanelNotifications;
use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Modules\Platform\Concerns\Auditable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform administrator (central DB, guard "admin").
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property-read string|null $avatar_url
 * @property string|null $password
 * @property AccountStatus $status
 * @property string|null $locale
 * @property string|null $timezone
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Admin extends Authenticatable implements HasLocalePreference, HasPanelInbox
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable, ReceivesPanelNotifications {
        ReceivesPanelNotifications::notifications insteadof Notifiable;
    }
    use SendsPasswordResetLink;

    /**
     * Disk of uploaded avatars (public URL).
     */
    public const AVATAR_DISK = 'public';

    protected $table = 'admins';

    protected string $guard_name = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'avatar_path', 'password', 'status', 'locale', 'timezone'];

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

    public function panelSurface(): string
    {
        return 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->attributes['avatar_path'] ?? null;

            return is_string($path) && $path !== '' ? Storage::disk(self::AVATAR_DISK)->url($path) : null;
        });
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
