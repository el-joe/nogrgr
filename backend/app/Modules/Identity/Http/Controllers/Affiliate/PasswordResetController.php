<?php

namespace App\Modules\Identity\Http\Controllers\Affiliate;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Controllers\Auth\PanelPasswordResetController;

/**
 * Affiliate forgot / reset password ({central}/affiliate/forgot-password, /affiliate/reset-password/{token}).
 */
class PasswordResetController extends PanelPasswordResetController
{
    protected function guard(): Guard
    {
        return Guard::Affiliate;
    }
}
