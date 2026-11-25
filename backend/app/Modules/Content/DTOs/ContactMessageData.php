<?php

namespace App\Modules\Content\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * Validated contact-form input (WEB-008). Phone is E.164 when given.
 */
final class ContactMessageData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $subject,
        public readonly string $message,
    ) {}
}
