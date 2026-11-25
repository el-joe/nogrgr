<?php

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One switch of a central user's notification matrix (category × channel).
 *
 * @property int $id
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property NotificationCategory $category
 * @property NotificationChannel $channel
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationPreference extends Model
{
    use CentralConnection;

    protected $table = 'notification_preferences';

    /**
     * @var list<string>
     */
    protected $fillable = ['notifiable_type', 'notifiable_id', 'category', 'channel', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'channel' => NotificationChannel::class,
            'enabled' => 'boolean',
        ];
    }
}
