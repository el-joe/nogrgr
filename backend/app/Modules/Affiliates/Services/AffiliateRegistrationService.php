<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\AffiliateRegistrationData;
use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Notifications\AffiliateVerifyEmailNotification;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Independent partner sign-up (AFF-002 / 003 / 006):
 *   register()  creates a *pending* affiliate (the admin activates it on ADM-060, which
 *               sends MAIL-038) and mails the e-mail confirmation link;
 *   verify()    signed link → email_verified_at (null for an expired / tampered link);
 *   resend()    a fresh link for an unconfirmed account (silent for unknown e-mails).
 */
class AffiliateRegistrationService extends BaseService
{
    public const LINK_MINUTES = 60;

    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly AffiliateAccountService $accounts,
        private readonly Auditor $auditor,
    ) {}

    public function register(AffiliateRegistrationData $data): Affiliate
    {
        $affiliate = $this->transaction(function () use ($data): Affiliate {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::withoutTenantScope()->create([
                'name' => $data->name,
                'email' => Str::lower($data->email),
                'phone' => $data->phone,
                'password' => $data->password,
                'locale' => $data->locale,
                'status' => AccountStatus::Pending,
                'type' => AffiliateType::Independent,
                'code' => $this->accounts->generateCode(),
            ]);

            return $affiliate;
        }, (new Affiliate)->getConnectionName());

        $this->accounts->ensurePanelRole($affiliate);
        $this->sendLink($affiliate);

        return $affiliate;
    }

    public function verify(Request $request, string $id, string $hash): ?Affiliate
    {
        if (! $request->hasValidSignature() || ! ctype_digit($id)) {
            return null;
        }

        $affiliate = $this->affiliates->findById((int) $id);

        if (! $affiliate instanceof Affiliate || $affiliate->email === null || ! hash_equals(sha1(Str::lower($affiliate->email)), $hash)) {
            return null;
        }

        if (! $affiliate->hasVerifiedEmail()) {
            $affiliate->markEmailAsVerified();
            $this->auditor->record(AuditAction::EmailVerified, $affiliate);
        }

        return $affiliate;
    }

    public function resend(string $email): void
    {
        $affiliate = Affiliate::withoutTenantScope()->where('email', Str::lower($email))->first();

        if ($affiliate instanceof Affiliate && ! $affiliate->hasVerifiedEmail()) {
            $this->sendLink($affiliate);
        }
    }

    private function sendLink(Affiliate $affiliate): void
    {
        if ($affiliate->email === null) {
            return;
        }

        $url = URL::temporarySignedRoute('affiliate.verification.verify', now()->addMinutes(self::LINK_MINUTES), [
            'id' => $affiliate->id,
            'hash' => sha1(Str::lower($affiliate->email)),
        ]);

        $affiliate->notify(new AffiliateVerifyEmailNotification($url, self::LINK_MINUTES));
    }
}
