<?php

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Enums\SystemRole;
use App\Modules\Identity\Events\AdminAccountCreated;
use App\Modules\Identity\Events\AdminPasswordChanged;
use App\Modules\Identity\Events\AdminRoleChanged;
use App\Modules\Identity\Notifications\AdminPasswordChangedNotification;
use App\Modules\Identity\Notifications\AdminRoleChangedNotification;
use App\Modules\Identity\Notifications\AdminTeamMemberAddedNotification;
use App\Modules\Identity\Notifications\AdminWelcomeNotification;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Shared\Support\Ui;

/**
 * Admin account events → panel notifications (database + mail + Reverb, per the
 * recipient's preferences) through the Notifications module.
 */
class SendAdminAccountNotifications
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
    ) {}

    public function created(AdminAccountCreated $event): void
    {
        $this->dispatcher->send($event->admin, new AdminWelcomeNotification($event->admin->name));

        $others = $this->admins->activeWithRole(SystemRole::SuperAdmin->value)
            ->reject(fn ($admin): bool => $admin->is($event->admin) || ($event->createdBy !== null && $admin->is($event->createdBy)));

        if ($others->isNotEmpty()) {
            $this->dispatcher->send($others, new AdminTeamMemberAddedNotification(
                $event->admin->name,
                (string) $event->admin->email,
                $event->createdBy->name ?? '—',
            ));
        }
    }

    public function passwordChanged(AdminPasswordChanged $event): void
    {
        $this->dispatcher->send($event->admin, new AdminPasswordChangedNotification(now()->toDateTimeString()));
    }

    public function roleChanged(AdminRoleChanged $event): void
    {
        $this->dispatcher->send($event->admin, new AdminRoleChangedNotification((string) Ui::roleLabel($event->to)));
    }
}
