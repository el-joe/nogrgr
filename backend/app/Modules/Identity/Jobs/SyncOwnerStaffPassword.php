<?php

namespace App\Modules\Identity\Jobs;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Owner changed their password in the owner portal (OWN-006): the owner's staff
 * account inside one store (same e-mail) takes the same password hash, so the central
 * store login keeps accepting the new password everywhere. One job per store; only
 * the bcrypt hash travels through the queue.
 */
class SyncOwnerStaffPassword implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $email,
        public readonly string $passwordHash,
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenant->run(function (): void {
            VendorStaff::query()
                ->where('email', strtolower($this->email))
                ->get()
                ->each(fn (VendorStaff $staff) => $staff->forceFill(['password' => $this->passwordHash])->saveQuietly());
        });
    }
}
