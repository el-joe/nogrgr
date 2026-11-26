<?php

namespace App\Modules\Ai\Policies;

use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Identity\Models\Admin;

/**
 * Admin AI translation runs: catalogue runs follow localization.languages.*, static key
 * runs follow localization.translations.* (view = progress, manage = retry / stop).
 */
class AdminTranslationRunPolicy
{
    public function view(Admin $admin, AdminTranslationRun $run): bool
    {
        return $admin->can($run->kind->viewPermission());
    }

    public function manage(Admin $admin, AdminTranslationRun $run): bool
    {
        return $admin->can($run->kind->managePermission());
    }
}
