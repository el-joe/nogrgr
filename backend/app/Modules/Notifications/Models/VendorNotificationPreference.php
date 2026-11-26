<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One switch of a store member's notification matrix (store database, VEN-087).
 *
 * @property int $id
 * @property int $vendor_staff_id
 * @property VendorNotificationTopic $topic
 * @property NotificationChannel $channel
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VendorNotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = ['vendor_staff_id', 'topic', 'channel', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic' => VendorNotificationTopic::class,
            'channel' => NotificationChannel::class,
            'enabled' => 'boolean',
        ];
    }
}
