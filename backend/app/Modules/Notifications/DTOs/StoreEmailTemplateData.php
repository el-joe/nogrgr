<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\EmailTemplateStatus;

/**
 * A store's customised template (VEN-080a): subject and sanitised HTML body per locale.
 */
final readonly class StoreEmailTemplateData
{
    /**
     * @param  array<string, string>  $subject
     * @param  array<string, string>  $body
     */
    public function __construct(
        public array $subject,
        public array $body,
        public EmailTemplateStatus $status = EmailTemplateStatus::Active,
    ) {}
}
