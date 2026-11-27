<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Notifications\DTOs\RenderedEmailData;
use App\Modules\Notifications\Support\MailPlainText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An e-mail built from an admin template (already rendered + sanitised), with its
 * plain-text alternative.
 */
class TemplatedMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly RenderedEmailData $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->email->subject);
    }

    public function content(): Content
    {
        return new Content(text: 'notifications::mail.rendered-text', with: ['text' => MailPlainText::fromHtml($this->email->html)], htmlString: $this->email->html);
    }
}
