<?php

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\Admin;
use Illuminate\Foundation\Events\Dispatchable;

final class AdminAccountCreated
{
    use Dispatchable;

    public function __construct(public readonly Admin $admin, public readonly ?Admin $createdBy) {}
}
