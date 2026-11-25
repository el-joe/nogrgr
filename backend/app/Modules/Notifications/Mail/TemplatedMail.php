<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Notifications\DTOs\RenderedEmailData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An e-mail built from an admin template (already rendered + sanitised).
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
        return new Content(htmlString: $this->email->html);
    }
}
