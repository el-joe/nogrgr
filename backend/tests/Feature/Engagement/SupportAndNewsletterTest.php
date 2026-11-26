<?php

use App\Modules\Engagement\Enums\SupportSubject;
use App\Modules\Engagement\Models\NewsletterSubscriber;
use App\Modules\Engagement\Models\SupportRequest;
use App\Modules\Engagement\Notifications\StoreEngagementNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Storefront support request + newsletter signup (SF-ECO-035): validation, E.164 phone,
| photos, ticket number, owners notified, duplicate newsletter signups, honeypot.
*/

beforeEach(function (): void {
    $this->tenant = $this->createTenant('helpdesk');
    $this->url = fn (string $path): string => $this->tenantUrl($this->tenant, $path);
});

it('stores a support request with photos and tells the store owners', function (): void {
    Notification::fake();
    Storage::fake('local');
    $owner = $this->createVendorOwner($this->tenant);

    $this->postJson(($this->url)('/support'), ['name' => '', 'email' => 'nope', 'subject' => 'unknown', 'message' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    $this->postJson(($this->url)('/support'), ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'other', 'message' => 'Buy cheap followers now', 'website' => 'spam.example'])
        ->assertUnprocessable()->assertJsonValidationErrors('website');

    $response = $this->post(($this->url)('/support'), [
        'name' => 'Fatma Elsonny',
        'email' => 'Fatma@Example.com',
        'phone' => '0551234567',
        'phone_country' => 'sa',
        'order_number' => '#10482',
        'subject' => SupportSubject::OrderIssue->value,
        'message' => 'My order arrived with a broken handle.',
        'attachments' => [UploadedFile::fake()->image('damage.jpg', 800, 600)],
    ], ['Accept' => 'application/json'])->assertCreated();

    $request = $this->tenant->run(fn () => SupportRequest::query()->sole());
    expect($request->phone)->toBe('+966551234567')
        ->and($request->email)->toBe('fatma@example.com')
        ->and($request->order_number)->toBe('10482')
        ->and($request->subject)->toBe(SupportSubject::OrderIssue)
        ->and($request->attachments)->toHaveCount(1)
        ->and($response->json('data.ticket'))->toBe($request->ticketNumber())
        ->and($response->json('message'))->toContain($request->ticketNumber());

    Notification::assertSentTo($owner, StoreEngagementNotification::class, fn (StoreEngagementNotification $notification): bool => $notification->eventName === 'support_request');
});

it('subscribes newsletter emails once', function (): void {
    $this->postJson(($this->url)('/newsletter'), ['email' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson(($this->url)('/newsletter'), ['email' => 'Reader@Example.com'])->assertCreated()->assertJsonPath('message', __st('support.newsletter.subscribed'));
    $this->postJson(($this->url)('/newsletter'), ['email' => 'reader@example.com'])->assertOk()->assertJsonPath('message', __st('support.newsletter.already'));

    $this->tenant->run(function (): void {
        expect(NewsletterSubscriber::query()->sole()->email)->toBe('reader@example.com');
        NewsletterSubscriber::query()->update(['unsubscribed_at' => now()]);
    });

    $this->postJson(($this->url)('/newsletter'), ['email' => 'reader@example.com'])->assertCreated();
    $this->tenant->run(fn () => expect(NewsletterSubscriber::query()->sole()->unsubscribed_at)->toBeNull());
});
