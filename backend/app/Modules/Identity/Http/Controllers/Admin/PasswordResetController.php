<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Controllers\Auth\PanelPasswordResetController;

/**
 * Admin forgot / reset password ({central}/admin/forgot-password, /admin/reset-password/{token}).
 */
class PasswordResetController extends PanelPasswordResetController
{
    protected function guard(): Guard
    {
        return Guard::Admin;
    }
}
