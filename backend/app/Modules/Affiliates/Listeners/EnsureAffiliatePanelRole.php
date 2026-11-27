<?php

namespace App\Modules\Affiliates\Listeners;

use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Identity\Enums\Guard;
use App\Modules\Identity\Models\Affiliate;
use Illuminate\Auth\Events\Login;

/**
 * Every affiliate that signs in to the partner panel holds the system "affiliate" role
 * (the panel permissions). Accounts created outside sign-up / SSO — the auto-created
 * vendor affiliate after a password reset, affiliates imported by legacy:migrate —
 * had no role and got 403 on every panel page (QA-FINAL-006).
 */
class EnsureAffiliatePanelRole
{
    public function __construct(private readonly AffiliateAccountService $accounts) {}

    public function handle(Login $event): void
    {
        if ($event->guard === Guard::Affiliate->value && $event->user instanceof Affiliate) {
            $this->accounts->ensurePanelRole($event->user);
        }
    }
}
