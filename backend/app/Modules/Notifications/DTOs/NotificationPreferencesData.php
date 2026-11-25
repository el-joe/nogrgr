<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Shared\DTOs\BaseDTO;

/**
 * Full preference matrix as submitted by the form (category => channel => bool).
 */
final class NotificationPreferencesData extends BaseDTO
{
    /**
     * @param  array<string, array<string, bool>>  $preferences
     */
    public function __construct(
        public readonly array $preferences,
    ) {}

    public function enabled(NotificationCategory $category, NotificationChannel $channel): bool
    {
        return (bool) ($this->preferences[$category->value][$channel->value] ?? false);
    }
}
