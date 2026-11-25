<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Concerns\SendsPasswordResetLink;
use App\Modules\Identity\Database\Factories\VendorStaffFactory;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Notifications\VendorVerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
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
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VendorStaff extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<VendorStaffFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SendsPasswordResetLink;

    public const VERIFY_LINK_MINUTES = 60;

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

    /**
     * Private Reverb channel of this member: staff ids repeat across store databases,
     * so the channel carries the tenant key (Notifications module channel auth).
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'tenant.'.(tenant()?->getTenantKey() ?? 'none').'.vendor.'.$this->getKey();
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => $this->freshTimestamp()])->save();
    }

    public function getEmailForVerification(): string
    {
        return (string) $this->email;
    }

    /**
     * Signed link to the store-host result page (identity::vendor.verify-result).
     */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute('vendor.verification.verify', now()->addMinutes(self::VERIFY_LINK_MINUTES), [
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
        ]);

        $this->notify(new VendorVerifyEmailNotification($url, self::VERIFY_LINK_MINUTES));
    }

    protected function passwordResetRoute(): string
    {
        return 'vendor.password.reset';
    }

    protected function passwordBroker(): string
    {
        return 'vendor_staff';
    }
}
