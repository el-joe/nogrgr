<?php

namespace App\Modules\Catalog\DTOs\Requests;

use App\Modules\Catalog\Enums\MessageSender;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * One message posted to a request conversation (admin reply now, vendor reply in VEN-0xx).
 */
final class RequestReplyData extends BaseDTO
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function __construct(
        public readonly MessageSender $sender,
        public readonly string $senderName,
        public readonly ?string $body,
        public readonly array $files = [],
        public readonly ?int $adminId = null,
    ) {}
}
