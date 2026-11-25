<?php

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Models\CustomerAddress;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Database\Eloquent\Collection;

/**
 * What checkout (Phase 5) needs from the address step: the customer's saved
 * addresses, the shipping address (requested → default → first) and the billing
 * address (same as shipping unless another one was chosen). Both are null when the
 * customer has no saved address ("No saved addresses found…", pay button disabled).
 */
final class CheckoutAddressSelectionData extends BaseDTO
{
    /**
     * @param  Collection<int, CustomerAddress>  $addresses
     */
    public function __construct(
        public readonly Customer $customer,
        public readonly Collection $addresses,
        public readonly ?CustomerAddress $shipping,
        public readonly ?CustomerAddress $billing,
        public readonly bool $billingSameAsShipping,
    ) {}

    public function hasAddress(): bool
    {
        return $this->shipping !== null;
    }

    public function shippingSnapshot(): ?OrderAddressSnapshotData
    {
        return $this->shipping !== null ? OrderAddressSnapshotData::fromAddress($this->shipping, $this->customer) : null;
    }

    public function billingSnapshot(): ?OrderAddressSnapshotData
    {
        $billing = $this->billingSameAsShipping ? $this->shipping : $this->billing;

        return $billing !== null ? OrderAddressSnapshotData::fromAddress($billing, $this->customer) : null;
    }
}
