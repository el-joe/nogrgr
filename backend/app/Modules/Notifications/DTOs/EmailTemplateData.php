<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Shared\DTOs\BaseDTO;

/**
 * Create / edit of an e-mail template. Bodies arrive sanitised (EmailHtmlSanitizer).
 */
final class EmailTemplateData extends BaseDTO
{
    /**
     * @param  array<string, string>  $subject  locale => subject
     * @param  array<string, string>  $body  locale => HTML
     */
    public function __construct(
        public readonly EmailTemplateEvent $event,
        public readonly array $subject,
        public readonly array $body,
        public readonly EmailTemplateStatus $status,
    ) {}
}
