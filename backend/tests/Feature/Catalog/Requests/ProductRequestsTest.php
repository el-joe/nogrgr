<?php

use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestPriority;
use App\Modules\Catalog\Events\Requests\RequestThreadUpdated;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/RequestHelpers.php';

/*
| Requests center › Product requests (ADM-018c/d): list + filters, detail with the shared
| conversation (read receipts, replies with private attachments, live push), status,
| priority, archive and the vendor notifications.
*/

beforeEach(function (): void {
    Storage::fake('central');
    [$this->tenant, $this->owner] = requestsStore();
    $this->request = ProductRequest::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Bamboo cutting board set']);
});

it('lists product requests with status, priority and archive filters', function (): void {
    ProductRequest::factory()->create(['tenant_id' => $this->tenant->id, 'status' => ProductRequestStatus::Rejected, 'priority' => RequestPriority::High]);
    ProductRequest::factory()->create(['tenant_id' => $this->tenant->id, 'archived_at' => now()]);
    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');

    $url = fn (string $query = ''): string => $this->centralUrl('/admin/requests/products/data?draw=1&start=0&length=25'.$query);

    expect($this->getJson($url())->assertOk()->json('recordsFiltered'))->toBe(2)
        ->and($this->getJson($url('&status=open'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&priority=high'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&archived=1'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&search=Bamboo'))->json('data.0.request'))->toContain('Bamboo cutting board set');
});

it('marks vendor messages read when the admin opens the request', function (): void {
    $message = $this->request->messages()->create(['tenant_id' => $this->tenant->id, 'sender' => MessageSender::Vendor, 'sender_name' => 'Megz Store', 'body' => 'Supplier link attached']);
    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');

    $this->get($this->centralUrl("/admin/requests/products/{$this->request->id}"))
        ->assertOk()
        ->assertSee('Supplier link attached')
        ->assertSee('data-thread', false)
        ->assertDontSee('data-thread-form', false);

    expect($message->refresh()->read_at)->not->toBeNull()
        ->and($this->request->refresh()->admin_seen_at)->not->toBeNull();
});

it('posts a reply with attachments, pushes a live update and notifies the store owner', function (): void {
    Notification::fake();
    Event::fake([RequestThreadUpdated::class]);
    $this->actingAs(catalogAdmin(['requests.products.manage']), 'admin');

    $this->post($this->centralUrl("/admin/requests/products/{$this->request->id}/messages"), [
        'body' => 'Thanks! Target retail price?',
        'files' => [UploadedFile::fake()->create('quote.pdf', 120, 'application/pdf')],
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.last_id', RequestMessage::query()->max('id'));

    $message = RequestMessage::query()->sole();
    $attachment = $message->attachments()->sole();

    expect($message->sender)->toBe(MessageSender::Admin)
        ->and($attachment->original_name)->toBe('quote.pdf')
        ->and($attachment->disk)->toBe('central');
    Storage::disk('central')->assertExists($attachment->path);
    Event::assertDispatched(RequestThreadUpdated::class, fn (RequestThreadUpdated $event): bool => $event->broadcastOn()->name === 'private-requests.product.'.$this->request->id);
    Notification::assertSentTo($this->owner, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'admin_replied');
});

it('validates replies and refuses view-only admins', function (): void {
    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');
    $this->postJson($this->centralUrl("/admin/requests/products/{$this->request->id}/messages"), ['body' => 'Hi'])->assertForbidden();

    $this->actingAs(catalogAdmin(['requests.products.manage']), 'admin');
    $this->postJson($this->centralUrl("/admin/requests/products/{$this->request->id}/messages"), ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->post($this->centralUrl("/admin/requests/products/{$this->request->id}/messages"), [
        'files' => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')],
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('files.0');
});

it('returns only new messages to the polling endpoint', function (): void {
    $first = $this->request->messages()->create(['tenant_id' => $this->tenant->id, 'sender' => MessageSender::Vendor, 'sender_name' => 'Megz', 'body' => 'First']);
    $this->request->messages()->create(['tenant_id' => $this->tenant->id, 'sender' => MessageSender::Vendor, 'sender_name' => 'Megz', 'body' => 'Second']);
    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');

    $this->getJson($this->centralUrl("/admin/requests/products/{$this->request->id}/messages?after={$first->id}"))
        ->assertOk()
        ->assertJsonPath('data.status.label', ProductRequestStatus::Pending->label())
        ->assertJson(fn ($json) => $json->where('data.html', fn (string $html): bool => str_contains($html, 'Second') && ! str_contains($html, 'First'))->etc());
});

it('streams attachments only to admins allowed to see the request type', function (): void {
    $message = $this->request->messages()->create(['tenant_id' => $this->tenant->id, 'sender' => MessageSender::Vendor, 'sender_name' => 'Megz', 'body' => null]);
    Storage::disk('central')->put('requests/product/1/photo.jpg', 'image-bytes');
    $attachment = RequestAttachment::query()->create(['message_id' => $message->id, 'disk' => 'local', 'path' => 'requests/product/1/photo.jpg', 'original_name' => 'board.jpg', 'mime_type' => 'image/jpeg', 'size' => 11]);
    $url = $this->centralUrl("/admin/requests/attachments/{$attachment->id}");

    $this->get($url)->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin(['requests.brands.view']), 'admin');
    $this->get($url)->assertForbidden();

    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');
    $response = $this->get($url)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get($url.'?download=1')->assertOk()->assertDownload('board.jpg');
    $this->get($this->centralUrl('/admin/requests/attachments/999999'))->assertNotFound();
});

it('changes status, priority and archive state', function (): void {
    Notification::fake();
    $this->actingAs(catalogAdmin(['requests.products.manage']), 'admin');
    $base = "/admin/requests/products/{$this->request->id}";

    $this->patchJson($this->centralUrl($base.'/status'), ['status' => 'quoted'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->patchJson($this->centralUrl($base.'/status'), ['status' => 'in_review'])->assertOk();
    $this->patchJson($this->centralUrl($base.'/priority'), ['priority' => 'urgent'])->assertOk();
    $this->patchJson($this->centralUrl($base.'/archive'), ['archived' => 1])->assertOk();

    $this->request->refresh();
    expect($this->request->status)->toBe(ProductRequestStatus::InReview)
        ->and($this->request->priority)->toBe(RequestPriority::Urgent)
        ->and($this->request->archived_at)->not->toBeNull();
    Notification::assertSentToTimes($this->owner, RequestUpdateNotification::class, 2);

    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');
    $this->patchJson($this->centralUrl($base.'/status'), ['status' => 'rejected'])->assertForbidden();
});

it('returns 404 for unknown requests', function (): void {
    $this->actingAs(catalogAdmin(['requests.products.view']), 'admin');
    $this->get($this->centralUrl('/admin/requests/products/999999'))->assertNotFound();
});
