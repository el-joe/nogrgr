<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central contact-form message (WEB-008); admins are notified (MAIL-041).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property string $locale
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContactMessage extends Model
{
    use CentralConnection;

    protected $table = 'contact_messages';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'locale', 'ip_address', 'user_agent', 'read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
