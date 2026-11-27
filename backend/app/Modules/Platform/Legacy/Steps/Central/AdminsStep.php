<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Old central `admins` → `admins` (bcrypt hashes kept, so passwords keep working;
 * matched by e-mail). The old "Super Admin" role maps to `super-admin`; custom old
 * roles have no permission equivalent, so those admins get no role (reported) and an
 * admin assigns one after cut-over.
 */
class AdminsStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Admins;
    }

    public function run(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $roles = $source->getSchemaBuilder()->hasTable('admin_roles') ? $source->table('admin_roles')->pluck('name', 'id')->all() : [];

        foreach ($source->table('admins')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'admins');
            $email = Str::lower(trim((string) $row->email));

            $id = $writer->upsert('admin', (int) $row->id, 'admins', [
                'name' => (string) $row->name,
                'email' => $email,
                'password' => is_string($row->password) && $row->password !== '' ? $row->password : Hash::make(Str::password(32)),
                'status' => ($row->status === 'active' ? AccountStatus::Active : AccountStatus::Suspended)->value,
                'last_login_at' => $row->last_login_at,
                'remember_token' => null,
                'created_at' => $row->created_at,
            ], ['email' => $email]);

            $role = $roles[$row->role_id] ?? null;

            if ($role === null || Str::slug((string) $role) !== 'super-admin') {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'admins', (int) $row->id, 'Old custom admin role not mapped (assign a role after cut-over)');

                continue;
            }

            if (! $context->dryRun()) {
                $admin = Admin::query()->find($id);

                if (! Role::query()->where(['name' => 'super-admin', 'guard_name' => 'admin'])->exists()) {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', 'admins', (int) $row->id, 'Role super-admin missing (run permissions:sync / seeders, then re-run the admins step)');
                } elseif ($admin !== null && ! $admin->hasRole('super-admin')) {
                    $admin->assignRole('super-admin');
                }
            }
        }
    }
}
