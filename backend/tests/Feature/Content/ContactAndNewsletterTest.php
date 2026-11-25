<?php

use App\Modules\Content\Enums\SubscriberStatus;
use App\Modules\Content\Models\ContactMessage;
use App\Modules\Content\Models\NewsletterSubscriber;
use App\Modules\Content\Notifications\ContactMessageMail;
use App\Modules\Content\Notifications\ContactMessageNotification;
use App\Modules\Content\Notifications\NewsletterConfirmationNotification;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\GeneralSettingsService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/ContentHelpers.php';

/*
| Contact form (WEB-008) and footer newsletter (WEB-015): validation, honeypot,
| throttle, admin notification + support inbox mail, double opt-in and unsubscribe.
*/

function contactPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Mona Khaled',
        'email' => 'mona@example.com',
        'phone' => '0501234567',
        'phone_country' => 'SA',
        'subject' => 'Custom domain question',
        'message' => 'Can I connect my own domain on the Standard plan?',
        'website' => '',
        'form_started_at' => now()->subMinute()->timestamp,
    ], $overrides);
}

it('validates the contact form', function (): void {
    $this->postJson($this->centralUrl('/contact'), contactPayload(['name' => '', 'email' => 'not-an-email', 'message' => 'short', 'phone' => '12']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'message', 'phone']);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('stores a contact message, notifies admins and mails the support inbox', function (): void {
    Notification::fake();
    $admin = contentAdmin(['content.contact.view']);
    contentAdmin([]);

    $this->postJson($this->centralUrl('/contact'), contactPayload())->assertOk()->assertJsonPath('success', true);

    $message = ContactMessage::query()->sole();
    expect($message->email)->toBe('mona@example.com')
        ->and($message->phone)->toBe('+966501234567');

    Notification::assertSentTo($admin, ContactMessageNotification::class);
    Notification::assertSentTimes(ContactMessageNotification::class, 1);
});

it('mails the support inbox from General settings', function (): void {
    Notification::fake();
    app(SettingsRepositoryContract::class)->put(GeneralSettingsService::GROUP, ['support_email' => 'support@example.com']);

    $this->postJson($this->centralUrl('/contact'), contactPayload())->assertOk();

    Notification::assertSentOnDemand(ContactMessageMail::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'support@example.com');
});

it('accepts but drops honeypot and too-fast contact submissions', function (): void {
    $this->postJson($this->centralUrl('/contact'), contactPayload(['website' => 'http://spam.test']))->assertOk();
    $this->postJson($this->centralUrl('/contact'), contactPayload(['form_started_at' => now()->timestamp]))->assertOk();

    expect(ContactMessage::query()->count())->toBe(0);
});

it('throttles the contact form', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson($this->centralUrl('/contact'), contactPayload(['email' => "user{$attempt}@example.com"]))->assertOk();
    }

    $this->postJson($this->centralUrl('/contact'), contactPayload())->assertTooManyRequests();
});

it('subscribes with double opt-in, confirms and unsubscribes', function (): void {
    Notification::fake();

    $this->postJson($this->centralUrl('/newsletter'), ['email' => 'Reader@Example.com'])
        ->assertOk()
        ->assertJsonPath('message', __('content::central.newsletter.pending'));

    $subscriber = NewsletterSubscriber::query()->sole();
    expect($subscriber->email)->toBe('reader@example.com')
        ->and($subscriber->status)->toBe(SubscriberStatus::Pending);
    Notification::assertSentTo($subscriber, NewsletterConfirmationNotification::class);

    $this->get($this->centralUrl('/newsletter/confirm/'.$subscriber->token))->assertOk();
    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Subscribed)
        ->and($subscriber->confirmed_at)->not->toBeNull();

    $this->postJson($this->centralUrl('/newsletter'), ['email' => 'reader@example.com'])
        ->assertOk()
        ->assertJsonPath('message', __('content::central.newsletter.already_subscribed'));
    Notification::assertSentToTimes($subscriber, NewsletterConfirmationNotification::class, 1);

    $this->get($this->centralUrl('/newsletter/unsubscribe/'.$subscriber->token))->assertOk();
    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    $this->get($this->centralUrl('/newsletter/confirm/'.$subscriber->token))->assertNotFound();
    $this->get($this->centralUrl('/newsletter/confirm/'.str_repeat('a', 64)))->assertNotFound();
});

it('validates, honeypots and throttles the newsletter signup', function (): void {
    Notification::fake();

    $this->postJson($this->centralUrl('/newsletter'), ['email' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson($this->centralUrl('/newsletter'), ['email' => 'bot@example.com', 'website' => 'x'])->assertOk();
    expect(NewsletterSubscriber::query()->count())->toBe(0);

    foreach (range(1, 3) as $attempt) {
        $this->postJson($this->centralUrl('/newsletter'), ['email' => "r{$attempt}@example.com"])->assertOk();
    }

    $this->postJson($this->centralUrl('/newsletter'), ['email' => 'r9@example.com'])->assertTooManyRequests();
});
