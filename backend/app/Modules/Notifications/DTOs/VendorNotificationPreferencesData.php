<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use App\Shared\DTOs\BaseDTO;

/**
 * A store member's full preference matrix as submitted (topic => channel => bool).
 */
final class VendorNotificationPreferencesData extends BaseDTO
{
    /**
     * @param  array<string, array<string, bool>>  $preferences
     */
    public function __construct(
        public readonly array $preferences,
    ) {}

    public function enabled(VendorNotificationTopic $topic, NotificationChannel $channel): bool
    {
        return (bool) ($this->preferences[$topic->value][$channel->value] ?? false);
    }
}
