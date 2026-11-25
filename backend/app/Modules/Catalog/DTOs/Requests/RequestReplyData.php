<?php

namespace App\Modules\Catalog\DTOs\Requests;

use App\Modules\Catalog\Enums\MessageSender;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * One message posted to a request conversation (admin or vendor reply). `silent` skips the
 * RequestMessagePosted notification (the opening message of a new request, which has
 * its own RequestSubmitted notification).
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
        public readonly bool $silent = false,
    ) {}
}
