<?php

namespace App\Modules\Notifications\Mail;

use App\Modules\Notifications\Support\MailPlainText;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Send test email" of Vendor › Settings › Mail (VEN-081): sent at once through the
 * store's SMTP so the vendor sees the result immediately.
 */
class StoreTestMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $storeName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('notifications::vendor_mail.test_subject', ['store' => $this->storeName]));
    }

    public function content(): Content
    {
        $text = MailPlainText::fromHtml(view('notifications::mail.store-test', ['storeName' => $this->storeName])->render());

        return new Content(view: 'notifications::mail.store-test', text: 'notifications::mail.rendered-text', with: ['storeName' => $this->storeName, 'text' => $text]);
    }
}
