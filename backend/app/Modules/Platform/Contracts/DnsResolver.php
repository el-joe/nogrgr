<?php

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Enums\DnsRecordType;

/**
 * Looks up public DNS records (bound to SystemDnsResolver; faked in tests).
 */
interface DnsResolver
{
    /**
     * Record values for host + type (A/AAAA → ip, CNAME/MX → target without trailing dot, TXT → text).
     *
     * @return list<string>
     */
    public function lookup(string $host, DnsRecordType $type): array;
}
