<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;

/**
 * Request attachments live on the private disk: an admin may open one with the view
 * permission of the request type it belongs to; a store member only for the store's own
 * requests (current tenant) and with the same view permission on the vendor guard.
 */
class RequestAttachmentPolicy
{
    public function view(Admin|VendorStaff $user, RequestAttachment $attachment): bool
    {
        $message = $attachment->message;
        $kind = RequestKind::fromMorphAlias((string) $message?->requestable_type);

        if ($kind === null || ! $user->can($kind->permission('view'))) {
            return false;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return tenancy()->initialized && (string) $message?->tenant_id === (string) tenant()?->getTenantKey();
    }
}
