<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Request conversations: messages, attachments, read receipts (central DB).
 *
 * @extends RepositoryContract<RequestMessage>
 */
interface RequestMessageRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFor(Model&ThreadedRequest $request, array $attributes): RequestMessage;

    /**
     * @param  array{disk: string, path: string, original_name: string, mime_type: string|null, size: int}  $attributes
     */
    public function addAttachment(RequestMessage $message, array $attributes): RequestAttachment;

    /**
     * Messages (with attachments) in order, optionally only those after a message id.
     *
     * @return Collection<int, RequestMessage>
     */
    public function thread(Model&ThreadedRequest $request, ?int $afterId = null): Collection;

    /**
     * Marks the other side's unread messages as read by $reader; returns how many changed.
     */
    public function markRead(Model&ThreadedRequest $request, MessageSender $reader): int;

    public function unreadFor(Model&ThreadedRequest $request, MessageSender $reader): int;

    public function findAttachment(int $id): ?RequestAttachment;

    /**
     * Every attachment of a request (Files card), newest first.
     *
     * @return Collection<int, RequestAttachment>
     */
    public function attachments(Model&ThreadedRequest $request): Collection;

    /**
     * Removes every message and attachment row of a request (files are deleted by the caller).
     */
    public function deleteThread(Model&ThreadedRequest $request): void;
}
