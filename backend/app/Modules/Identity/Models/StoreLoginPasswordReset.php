<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Hashed reset token of the central store login (WEB-023 / 024).
 *
 * @property string $email
 * @property string $token
 * @property Carbon|null $created_at
 */
class StoreLoginPasswordReset extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'store_login_password_resets';

    protected $primaryKey = 'email';

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = ['email', 'token', 'created_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
