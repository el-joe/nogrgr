<?php

namespace App\Modules\Support\DTOs;

use App\Modules\Engagement\Enums\SupportSubject;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * A new customer ticket (Store API: app + theme account): topic, subject, first message,
 * optional own order and up to 5 files.
 */
final class OpenCustomerTicketData extends BaseDTO
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public readonly SupportSubject $topic,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?string $orderUuid = null,
        public readonly array $files = [],
    ) {}
}
