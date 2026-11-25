<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Enums\DnsRecordType;
use App\Shared\DTOs\BaseDTO;

final class DnsRecordTemplateData extends BaseDTO
{
    public function __construct(
        public readonly DnsRecordType $type,
        public readonly string $host,
        public readonly string $value,
        public readonly int $ttl = 3600,
        public readonly ?int $priority = null,
        public readonly int $sortOrder = 100,
    ) {}
}
