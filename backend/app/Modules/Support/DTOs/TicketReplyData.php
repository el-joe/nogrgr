<?php

namespace App\Modules\Support\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * One message on a ticket; `internal` = admin-only note.
 */
final class TicketReplyData extends BaseDTO
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public readonly TicketActor $actor,
        public readonly ?string $body,
        public readonly array $files = [],
        public readonly bool $internal = false,
    ) {}
}
