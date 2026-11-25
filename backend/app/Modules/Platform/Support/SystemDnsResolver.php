<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Contracts\DnsResolver;
use App\Modules\Platform\Enums\DnsRecordType;

final class SystemDnsResolver implements DnsResolver
{
    public function lookup(string $host, DnsRecordType $type): array
    {
        $records = @dns_get_record($host, $type->dnsConstant());

        if (! is_array($records)) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            $value = match ($type) {
                DnsRecordType::A => $record['ip'] ?? null,
                DnsRecordType::AAAA => $record['ipv6'] ?? null,
                DnsRecordType::CNAME, DnsRecordType::MX => $record['target'] ?? null,
                DnsRecordType::TXT => $record['txt'] ?? null,
            };

            if (is_string($value) && $value !== '') {
                $values[] = strtolower(rtrim($value, '.'));
            }
        }

        return array_values(array_unique($values));
    }
}
