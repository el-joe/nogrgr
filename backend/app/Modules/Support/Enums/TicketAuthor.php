<?php

namespace App\Modules\Support\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Who wrote a ticket message / opened a ticket: platform admin, store staff (vendor panel)
 * or the store owner (owner portal). Vendor and owner are both "the requester".
 */
enum TicketAuthor: string
{
    use HasEnumHelpers;

    case Admin = 'admin';
    case Vendor = 'vendor';
    case Owner = 'owner';

    public function isRequester(): bool
    {
        return $this !== self::Admin;
    }
}
