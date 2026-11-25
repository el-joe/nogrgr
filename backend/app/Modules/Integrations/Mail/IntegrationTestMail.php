<?php

namespace App\Modules\Integrations\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Send test email" of Admin › Integrations › Mail: sent synchronously through the
 * saved transport so delivery errors reach the admin immediately.
 */
class IntegrationTestMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $platformName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('integrations::admin.mail.test_subject', ['name' => $this->platformName]));
    }

    public function content(): Content
    {
        return new Content(view: 'integrations::mail.test', with: ['platformName' => $this->platformName]);
    }
}
