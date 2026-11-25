<?php

namespace App\Modules\AfterSales\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * A customer reply on the return thread (SF-ECO-032 / APP-018) with optional attachments.
 */
final class ReturnMessageData extends BaseDTO
{
    /**
     * @param  list<UploadedFile>  $photos
     */
    public function __construct(
        public readonly string $message,
        public readonly array $photos = [],
        public readonly ?UploadedFile $video = null,
    ) {}
}
