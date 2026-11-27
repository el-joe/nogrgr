<?php

namespace App\Modules\Inventory\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * "Notify me when it is back" input: the guest e-mail (ignored for a signed-in
 * customer, who subscribes with the account e-mail) and the shopper's language.
 */
final class StockAlertSubscribeData extends BaseDTO
{
    public function __construct(
        public readonly ?string $email,
        public readonly string $locale,
    ) {}
}
