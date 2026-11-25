<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\DTOs\Requests\RequestReplyData;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Events\Requests\RequestMessagePosted;
use App\Modules\Catalog\Events\Requests\RequestThreadUpdated;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Repositories\Contracts\RequestMessageRepositoryContract;
use App\Modules\Catalog\Support\ThreadedRequestRepositories;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The one conversation engine of the Requests center (product, manufacturing and brand
 * requests): post messages with attachments (private disk), read receipts, unread counts,
 * secure file streaming and the live "thread updated" push over Reverb.
 */
class RequestThreadService extends BaseService
{
    /**
     * Central private disk (not re-rooted by tenancy): vendor uploads from the store panel
     * and admin uploads land in the same place. Older rows say "local" (same root).
     */
    public const DISK = 'central';

    public function __construct(
        private readonly RequestMessageRepositoryContract $messages,
        private readonly ThreadedRequestRepositories $requests,
    ) {}

    /**
     * @return Collection<int, RequestMessage>
     */
    public function messages(Model&ThreadedRequest $request, ?int $afterId = null): Collection
    {
        return $this->messages->thread($request, $afterId);
    }

    /**
     * @return Collection<int, RequestAttachment>
     */
    public function attachments(Model&ThreadedRequest $request): Collection
    {
        return $this->messages->attachments($request);
    }

    public function post(Model&ThreadedRequest $request, RequestReplyData $reply): RequestMessage
    {
        $body = $reply->body !== null ? trim($reply->body) : '';

        if ($body === '' && $reply->files === []) {
            throw RequestRuleException::emptyMessage();
        }

        $stored = [];

        try {
            $message = $this->transaction(function () use ($request, $reply, $body, &$stored): RequestMessage {
                $message = $this->messages->createFor($request, [
                    'sender' => $reply->sender,
                    'admin_id' => $reply->adminId,
                    'sender_name' => $reply->senderName,
                    'body' => $body !== '' ? $body : null,
                ]);

                $directory = 'requests/'.$request->kind()->value.'/'.$request->getKey();

                foreach ($reply->files as $file) {
                    $path = $file->storeAs($directory, Str::uuid()->toString().'.'.($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'), self::DISK);

                    if ($path === false) {
                        throw RequestRuleException::attachmentMissing();
                    }

                    $stored[] = $path;
                    $this->messages->addAttachment($message, [
                        'disk' => self::DISK,
                        'path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
                        'mime_type' => $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                    ]);
                }

                $attributes = ['last_message_at' => now()];

                if ($reply->sender === MessageSender::Admin) {
                    $attributes['admin_seen_at'] = now();
                }

                $this->requests->update($request, $attributes);

                if (! $reply->silent) {
                    $this->dispatchAfterCommit(new RequestMessagePosted($request, $message));
                }

                return $message;
            }, $request->getConnectionName());
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                self::disk(self::DISK)->delete($path);
            }

            throw $exception;
        }

        $this->pushUpdate($request, 'message', $message->id);

        return $message->load('attachments');
    }

    /**
     * The admin opened the request: vendor messages become read, the request seen.
     */
    public function openByAdmin(Model&ThreadedRequest $request): void
    {
        $changed = $this->messages->markRead($request, MessageSender::Admin);
        $this->requests->update($request, ['admin_seen_at' => now()]);

        if ($changed > 0) {
            $this->pushUpdate($request, 'read');
        }
    }

    /**
     * A store member opened the request: platform messages become read for the store.
     */
    public function openByVendor(Model&ThreadedRequest $request): void
    {
        if ($this->messages->markRead($request, MessageSender::Vendor) > 0) {
            $this->pushUpdate($request, 'read');
        }
    }

    /**
     * Deletes the conversation of a request with its files (request deleted by the store).
     */
    public function purge(Model&ThreadedRequest $request): void
    {
        $paths = $this->messages->attachments($request)->map(static fn (RequestAttachment $attachment): array => [$attachment->disk, $attachment->path])->all();
        $this->messages->deleteThread($request);

        foreach ($paths as [$disk, $path]) {
            self::disk($disk)->delete($path);
        }
    }

    public function unreadFor(Model&ThreadedRequest $request, MessageSender $reader): int
    {
        return $this->messages->unreadFor($request, $reader);
    }

    public function findAttachment(int $id): ?RequestAttachment
    {
        return $this->messages->findAttachment($id);
    }

    /**
     * Streams an attachment from the private disk (inline for images/PDF, else download).
     */
    public function stream(RequestAttachment $attachment, bool $download = false): StreamedResponse
    {
        $disk = self::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            throw new NotFoundHttpException((string) __('catalog::requests.errors.attachment_missing'));
        }

        $headers = [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        $inline = ! $download && ($attachment->isImage() || $attachment->mime_type === 'application/pdf');

        return $inline
            ? $disk->response($attachment->path, $attachment->original_name, $headers, 'inline')
            : $disk->download($attachment->path, $attachment->original_name, $headers);
    }

    /**
     * Reverb (Pusher protocol) settings for the live thread of an admin or vendor page, or
     * null when broadcasting is not Reverb — the page then polls.
     *
     * @return array{key: string, host: string, port: int, tls: bool, channel: string, authUrl: string, event: string}|null
     */
    public function realtimeConfig(Model&ThreadedRequest $request, string $surface = 'admin'): ?array
    {
        $key = config('broadcasting.connections.reverb.key');
        $authRoute = $surface === 'vendor' ? 'vendor.requests.broadcast-auth' : 'admin.notifications.broadcast-auth';

        if (config('broadcasting.default') !== 'reverb' || ! is_string($key) || $key === '' || ! Route::has($authRoute)) {
            return null;
        }

        $scheme = (string) config('broadcasting.connections.reverb.options.scheme', 'https');

        return [
            'key' => $key,
            'host' => (string) (config('broadcasting.connections.reverb.options.host') ?: request()->getHost()),
            'port' => (int) config('broadcasting.connections.reverb.options.port', $scheme === 'https' ? 443 : 80),
            'tls' => $scheme === 'https',
            'channel' => 'private-requests.'.$request->kind()->value.'.'.$request->getKey(),
            'authUrl' => $surface === 'vendor' ? vendor_route('requests.broadcast-auth') : route($authRoute),
            'event' => 'request.updated',
        ];
    }

    /**
     * Tells open request pages (admin and vendor) to refresh.
     */
    public function pushUpdate(Model&ThreadedRequest $request, string $reason, int $lastMessageId = 0): void
    {
        event(new RequestThreadUpdated($request->kind()->value, (int) $request->getKey(), $lastMessageId, $reason));
    }

    /**
     * The disk of a stored attachment; legacy "local" rows live on the same central root.
     */
    private static function disk(string $name): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($name === 'local' ? self::DISK : $name);

        return $disk;
    }
}
