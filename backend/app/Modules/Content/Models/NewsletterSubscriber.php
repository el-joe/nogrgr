<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Footer newsletter subscriber (WEB-015, ADM-084): pending until the e-mail link is
 * opened (double opt-in); the same token unsubscribes.
 *
 * @property int $id
 * @property string $email
 * @property string $locale
 * @property SubscriberStatus $status
 * @property string $token
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NewsletterSubscriber extends Model
{
    use CentralConnection;
    use Notifiable;

    protected $table = 'newsletter_subscribers';

    /**
     * @var list<string>
     */
    protected $fillable = ['email', 'locale', 'status', 'token', 'confirmation_sent_at', 'confirmed_at', 'unsubscribed_at', 'ip_address'];

    /**
     * @var list<string>
     */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
