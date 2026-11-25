<?php

namespace App\Modules\Identity\Http\Controllers\Affiliate;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Controllers\Auth\PanelLoginController;

/**
 * Affiliate partner sign-in ({central}/affiliate/login) and sign-out.
 */
class LoginController extends PanelLoginController
{
    protected function guard(): Guard
    {
        return Guard::Affiliate;
    }
}
