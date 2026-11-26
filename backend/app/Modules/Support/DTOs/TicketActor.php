<?php

namespace App\Modules\Support\DTOs;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\Enums\TicketAuthor;
use App\Shared\DTOs\BaseDTO;

/**
 * Who acts on a ticket: a platform admin, a store staff member or the owner account.
 */
final class TicketActor extends BaseDTO
{
    public function __construct(
        public readonly TicketAuthor $author,
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $email = null,
    ) {}

    public static function admin(Admin $admin): self
    {
        return new self(TicketAuthor::Admin, (int) $admin->id, (string) $admin->name, (string) $admin->email);
    }

    public static function vendor(VendorStaff $staff): self
    {
        return new self(TicketAuthor::Vendor, (int) $staff->id, (string) $staff->name, (string) $staff->email);
    }

    public static function owner(Owner $owner): self
    {
        return new self(TicketAuthor::Owner, (int) $owner->id, (string) $owner->name, (string) $owner->email);
    }
}
