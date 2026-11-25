<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use App\Modules\Catalog\Repositories\Contracts\RequestMessageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<RequestMessage>
 */
final class RequestMessageRepository extends BaseRepository implements RequestMessageRepositoryContract
{
    protected function model(): string
    {
        return RequestMessage::class;
    }

    public function query(): Builder
    {
        return RequestMessage::withoutTenantScope();
    }

    public function createFor(Model&ThreadedRequest $request, array $attributes): RequestMessage
    {
        /** @var RequestMessage $message */
        $message = $this->query()->create([
            ...$attributes,
            'requestable_type' => $request->getMorphClass(),
            'requestable_id' => $request->getKey(),
            'tenant_id' => $request->tenantId(),
        ]);

        return $message;
    }

    public function addAttachment(RequestMessage $message, array $attributes): RequestAttachment
    {
        /** @var RequestAttachment $attachment */
        $attachment = $message->attachments()->create($attributes);

        return $attachment;
    }

    public function thread(Model&ThreadedRequest $request, ?int $afterId = null): Collection
    {
        return $this->forRequest($request)
            ->with('attachments')
            ->when($afterId !== null, static fn (Builder $query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();
    }

    public function markRead(Model&ThreadedRequest $request, MessageSender $reader): int
    {
        return $this->forRequest($request)
            ->where('sender', '!=', $reader->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadFor(Model&ThreadedRequest $request, MessageSender $reader): int
    {
        return $this->forRequest($request)->where('sender', '!=', $reader->value)->whereNull('read_at')->count();
    }

    public function findAttachment(int $id): ?RequestAttachment
    {
        return RequestAttachment::query()->with('message')->find($id);
    }

    public function attachments(Model&ThreadedRequest $request): Collection
    {
        return RequestAttachment::query()
            ->whereIn('message_id', $this->forRequest($request)->select('id'))
            ->orderByDesc('id')
            ->get();
    }

    public function deleteThread(Model&ThreadedRequest $request): void
    {
        RequestAttachment::query()->whereIn('message_id', $this->forRequest($request)->select('id'))->delete();
        $this->forRequest($request)->delete();
    }

    /**
     * @return Builder<RequestMessage>
     */
    private function forRequest(Model&ThreadedRequest $request): Builder
    {
        return $this->query()
            ->where('requestable_type', $request->getMorphClass())
            ->where('requestable_id', $request->getKey());
    }
}
