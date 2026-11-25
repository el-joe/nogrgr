<?php

namespace App\Modules\Commerce\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Fulfilment files of an order (order_attachments.kind).
 */
enum AttachmentKind: string
{
    use HasEnumHelpers;

    case ShippingLabel = 'shipping_label';
    case PackingProof = 'packing_proof';
    case Customs = 'customs';
    case Other = 'other';
}
