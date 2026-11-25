<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum DomainType: string
{
    use HasEnumHelpers;

    /** Stored as the bare label, e.g. "acme" → acme.{central-domain}. */
    case Subdomain = 'subdomain';

    /** Stored as the full host, e.g. "shop.example.com". */
    case Custom = 'custom';
}
