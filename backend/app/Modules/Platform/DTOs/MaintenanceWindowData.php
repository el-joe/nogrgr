<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Enums\MaintenanceScope;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * A maintenance window as entered by an admin (ADM-113). tenantId only for scope
 * "store"; endsAt null = until ended by hand; allowedIps = IPs / CIDR ranges.
 */
final class MaintenanceWindowData extends BaseDTO
{
    /**
     * @param  array{en: string|null, ar: string|null}  $message
     * @param  list<string>  $allowedIps
     */
    public function __construct(
        public readonly MaintenanceScope $scope,
        public readonly ?int $tenantId,
        public readonly string $title,
        public readonly ?string $notes,
        public readonly array $message,
        public readonly array $allowedIps,
        public readonly Carbon $startsAt,
        public readonly ?Carbon $endsAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $message = array_filter($this->message, static fn (?string $text): bool => $text !== null && trim($text) !== '');

        return [
            'scope' => $this->scope,
            'tenant_id' => $this->scope === MaintenanceScope::Store ? $this->tenantId : null,
            'title' => $this->title,
            'notes' => $this->notes,
            'message' => $message === [] ? null : $message,
            'allowed_ips' => $this->allowedIps === [] ? null : array_values($this->allowedIps),
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
