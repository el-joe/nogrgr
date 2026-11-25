<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum DnsRecordType: string
{
    use HasEnumHelpers;

    case A = 'A';
    case AAAA = 'AAAA';
    case CNAME = 'CNAME';
    case TXT = 'TXT';
    case MX = 'MX';

    public function requiresPriority(): bool
    {
        return $this === self::MX;
    }

    /**
     * PHP dns_get_record() type constant.
     */
    public function dnsConstant(): int
    {
        return match ($this) {
            self::A => DNS_A,
            self::AAAA => DNS_AAAA,
            self::CNAME => DNS_CNAME,
            self::TXT => DNS_TXT,
            self::MX => DNS_MX,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::A, self::CNAME => 'brand',
            self::AAAA => 'warning',
            self::TXT, self::MX => 'info',
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
