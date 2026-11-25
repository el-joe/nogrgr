<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * SMTP security: implicit TLS (ssl, usually port 465) or STARTTLS/plain (tls, none).
 */
enum MailEncryption: string
{
    use HasEnumHelpers;

    case Tls = 'tls';
    case Ssl = 'ssl';
    case None = 'none';

    /**
     * Symfony mailer DSN scheme for Laravel's `mail.mailers.smtp.scheme`.
     */
    public function scheme(): string
    {
        return $this === self::Ssl ? 'smtps' : 'smtp';
    }
}
