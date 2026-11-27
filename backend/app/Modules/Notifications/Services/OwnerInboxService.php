<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Notifications\Notifications\OwnerInboxNotification;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;

/**
 * Puts a copy of an owner e-mail into the owner-portal inbox of the store's owner
 * account (central DB). Stores without an owner account (legacy e-mail only) get none.
 */
class OwnerInboxService extends BaseService
{
    public function __construct(
        private readonly OwnerRepositoryContract $owners,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function send(?Tenant $tenant, OwnerInboxNotification $notification): void
    {
        $owner = $this->ownerOf($tenant);

        if ($owner !== null) {
            $send = fn () => $this->dispatcher->send($owner, $notification);
            tenancy()->initialized ? tenancy()->central($send) : $send();
        }
    }

    private function ownerOf(?Tenant $tenant): ?Owner
    {
        $ownerId = $tenant?->getAttribute('owner_id');

        if ($ownerId !== null) {
            $owner = $this->owners->find((int) $ownerId);

            if ($owner instanceof Owner) {
                return $owner;
            }
        }

        $email = $tenant?->getAttribute('owner_email');

        return is_string($email) && $email !== '' ? $this->owners->findByEmail($email) : null;
    }
}
