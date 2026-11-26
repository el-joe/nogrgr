<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Requests\Admin\RoleRequest;

/**
 * Vendor › Settings › Role create / edit (VEN-077a): same rules as the admin role
 * editor, against the store's "vendor" guard and the tenant permission catalog.
 */
class VendorRoleRequest extends RoleRequest
{
    protected function guard(): string
    {
        return Guard::Vendor->value;
    }
}
