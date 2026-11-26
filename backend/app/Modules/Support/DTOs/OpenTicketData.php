<?php

namespace App\Modules\Support\DTOs;

use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * A new support ticket from the vendor panel (tenantId = the current store) or the owner
 * portal (ownerId set; tenantId = the picked store or null for an account question).
 */
final class OpenTicketData extends BaseDTO
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public readonly TicketActor $actor,
        public readonly ?int $tenantId,
        public readonly ?int $ownerId,
        public readonly string $subject,
        public readonly TicketCategory $category,
        public readonly TicketPriority $priority,
        public readonly string $body,
        public readonly array $files = [],
    ) {}
}
