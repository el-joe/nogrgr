<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\AdminProfileData;
use App\Modules\Identity\Events\AdminPasswordChanged;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Support\Facades\Storage;

/**
 * My profile (ADM-005): name, email, language, timezone, avatar and password change
 * (the current password is checked by the FormRequest). Field diffs are audited by the
 * Auditable trait; the password change is audited as auth.password_changed.
 */
class AdminProfileService extends BaseService
{
    public const AVATAR_DIRECTORY = 'admins/avatars';

    public function __construct(
        private readonly AdminRepositoryContract $admins,
        private readonly Auditor $auditor,
    ) {}

    public function update(Admin $admin, AdminProfileData $data): Admin
    {
        $attributes = [
            'name' => $data->name,
            'email' => strtolower($data->email),
            'locale' => $data->locale,
            'timezone' => $data->timezone,
        ];
        $previous = $admin->getAttributes()['avatar_path'] ?? null;

        if ($data->avatar !== null) {
            $attributes['avatar_path'] = $data->avatar->store(self::AVATAR_DIRECTORY, Admin::AVATAR_DISK);
        } elseif (! $data->keepAvatar) {
            $attributes['avatar_path'] = null;
        }

        $this->admins->update($admin, $attributes);

        if (array_key_exists('avatar_path', $attributes) && $previous !== null && $previous !== $attributes['avatar_path']) {
            Storage::disk(Admin::AVATAR_DISK)->delete($previous);
        }

        return $admin->refresh();
    }

    public function changePassword(Admin $admin, string $password): void
    {
        $this->admins->update($admin, ['password' => $password]);
        $this->auditor->record(AuditAction::PasswordChanged, $admin);

        AdminPasswordChanged::dispatch($admin, true);
    }
}
