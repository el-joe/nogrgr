<?php

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Admin;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an admin's password changes (own profile or by another admin).
 */
final class AdminPasswordChanged
{
    use Dispatchable;

    public function __construct(public readonly Admin $admin, public readonly bool $bySelf) {}
}
