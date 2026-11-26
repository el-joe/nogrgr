<?php

namespace App\Modules\Support\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Support ticket lifecycle (ADM-116):
 *  - open: new, or the requester (store / owner) wrote last — waiting on the platform;
 *  - awaiting_reply: the platform needs information from the store ("Waiting on vendor");
 *  - answered: the platform replied — waiting on the requester;
 *  - resolved: solved; a new requester message reopens it;
 *  - closed: archived; only an explicit "Reopen" brings it back.
 */
enum TicketStatus: string
{
    use HasEnumHelpers;

    case Open = 'open';
    case AwaitingReply = 'awaiting_reply';
    case Answered = 'answered';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Admin list tab of the status ("waiting" groups awaiting_reply + answered).
     */
    public function tab(): string
    {
        return match ($this) {
            self::Open => 'open',
            self::AwaitingReply, self::Answered => 'waiting',
            self::Resolved => 'resolved',
            self::Closed => 'closed',
        };
    }

    /**
     * @return list<self>
     */
    public static function forTab(string $tab): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->tab() === $tab));
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::AwaitingReply, self::Answered], true);
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Label for the store / owner side ("Needs your reply" instead of "Waiting on vendor").
     */
    public function requesterLabel(): string
    {
        $key = 'support::enums.ticket_status_requester.'.$this->value;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $this->label();
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::AwaitingReply => 'warning',
            self::Answered => 'brand',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }
}
