<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Http\Controllers\Auth\PanelLoginController;
use App\Modules\Platform\Services\AdminDashboardService;

/**
 * Admin sign-in ({central}/admin/login) and sign-out.
 */
class LoginController extends PanelLoginController
{
    protected function guard(): Guard
    {
        return Guard::Admin;
    }

    protected function pageData(): array
    {
        return ['stats' => app(AdminDashboardService::class)->signInStats()];
    }
}
