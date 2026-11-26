<?php

namespace App\Modules\Notifications\DTOs;

use App\Modules\Integrations\Enums\MailEncryption;

/**
 * Store SMTP (Vendor › Settings › Mail, VEN-081). `password` null = keep the saved one.
 */
final readonly class StoreMailSettingsData
{
    public function __construct(
        public string $host,
        public int $port,
        public MailEncryption $encryption,
        public ?string $username,
        public ?string $password,
        public string $fromAddress,
        public ?string $fromName,
    ) {}
}
