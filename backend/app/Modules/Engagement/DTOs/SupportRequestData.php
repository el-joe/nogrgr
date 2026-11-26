<?php

namespace App\Modules\Engagement\DTOs;

use App\Modules\Engagement\Enums\SupportSubject;
use Illuminate\Http\UploadedFile;

/**
 * Storefront support request (SF-ECO-035). Phone already normalised to E.164.
 */
final readonly class SupportRequestData
{
    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function __construct(
        public string $name,
        public string $email,
        public SupportSubject $subject,
        public string $message,
        public ?string $phone = null,
        public ?string $orderNumber = null,
        public array $attachments = [],
        public ?int $customerId = null,
        public ?string $locale = null,
        public ?string $ipAddress = null,
    ) {}
}
