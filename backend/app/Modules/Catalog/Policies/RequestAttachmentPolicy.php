<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\RequestAttachment;
use App\Modules\Identity\Models\Admin;

/**
 * Request attachments live on the private disk: an admin may open one only with the
 * view permission of the request type it belongs to (vendor access lands with VEN-0xx,
 * limited to the store's own requests).
 */
class RequestAttachmentPolicy
{
    public function view(Admin $admin, RequestAttachment $attachment): bool
    {
        $kind = RequestKind::fromMorphAlias((string) $attachment->message?->requestable_type);

        return $kind !== null && $admin->can($kind->permission('view'));
    }
}
