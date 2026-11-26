<?php

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store newsletter subscriber (tenant DB, footer signup SF-ECO-035).
 *
 * @property int $id
 * @property string $email
 * @property string|null $locale
 * @property int|null $customer_id
 * @property Carbon $subscribed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NewsletterSubscriber extends Model
{
    protected $table = 'store_newsletter_subscribers';

    /**
     * @var list<string>
     */
    protected $fillable = ['email', 'locale', 'customer_id', 'subscribed_at', 'unsubscribed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
