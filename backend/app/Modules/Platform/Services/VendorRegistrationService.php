<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Services\OwnerService;
use App\Modules\Platform\Contracts\DnsResolver;
use App\Modules\Platform\DTOs\PendingRegistrationData;
use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\DTOs\RegistrationAccountData;
use App\Modules\Platform\DTOs\RegistrationStoreData;
use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Events\RegistrationCompleted;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Notifications\RegistrationLinkNotification;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Services\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Central-website registration (Platform › Registration), one row per sign-up:
 *
 *   createPending()   sign-up form (WEB-016) — status email_unverified or payment_pending
 *   sendLink()        e-mails the signed completion link (WEB-017 / admin "resend link")
 *   resolveToken()    completion page entry (WEB-018); open() marks the e-mail verified
 *   saveAccount()     account step (owner name + password)
 *   complete()        store step (WEB-018a) → TenantProvisioningService::provision()
 *
 * Tokens are random, e-mailed once and stored as SHA-256 hashes only; the URL is also
 * signed with the same expiry, so a tampered or stale link never reaches the form.
 */
class VendorRegistrationService extends BaseService
{
    public function __construct(
        private readonly VendorRegistrationRepositoryContract $registrations,
        private readonly TenantRepositoryContract $tenants,
        private readonly DomainRequestRepositoryContract $domainRequests,
        private readonly TenantProvisioningService $provisioning,
        private readonly DnsRecordTemplateRepositoryContract $dnsTemplates,
        private readonly DnsResolver $dns,
        private readonly OwnerService $owners,
    ) {}

    public function linkLifetimeHours(): int
    {
        return max(1, (int) config('platform.registration_link_hours', 72));
    }

    /**
     * @return array<string, int> all pending + per pending status
     */
    public function counts(): array
    {
        $counts = ['all' => $this->registrations->countPending()];

        foreach (array_keys(RegistrationStatus::pendingOptions()) as $status) {
            $counts[$status] = $this->registrations->pending(['status' => $status])->count();
        }

        return $counts;
    }

    public function createPending(PendingRegistrationData $data): VendorRegistration
    {
        /** @var VendorRegistration $registration */
        $registration = $this->registrations->create([
            'owner_id' => $data->ownerId,
            'email' => Str::lower($data->email),
            'phone' => $data->phone,
            'plan_id' => $data->planId,
            'term' => $data->term,
            'locale' => $data->locale,
            'status' => $data->status,
            'owner_name' => $data->ownerName,
            'password' => $data->ownerPasswordHash,
            'store_name' => $data->storeName,
            'slug' => $data->slug !== null ? Str::lower($data->slug) : null,
            'email_verified_at' => $data->emailVerified ? now() : null,
            'expires_at' => now()->addHours($this->linkLifetimeHours()),
            'meta' => $data->meta === [] ? null : $data->meta,
        ]);

        return $registration;
    }

    /**
     * New token + expiry, e-mails the completion link and returns it.
     */
    public function sendLink(VendorRegistration $registration): string
    {
        return $this->issueLink($registration, true);
    }

    /**
     * New token + expiry and the completion link for it. E-mailed (and counted towards
     * the resend limit) unless the e-mail is already proven and the visitor is sent
     * straight on (Google / Apple sign-up, signed-in owner).
     */
    public function issueLink(VendorRegistration $registration, bool $notify = true): string
    {
        if (! $registration->status->canSendLink()) {
            throw PlatformRuleException::linkNotAllowed();
        }

        $token = Str::random(48);
        $expiresAt = now()->addHours($this->linkLifetimeHours());

        $this->registrations->update($registration, array_filter([
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'link_sent_at' => $notify ? now() : null,
            'link_sent_count' => $notify ? $registration->link_sent_count + 1 : null,
        ], static fn (mixed $value): bool => $value !== null));

        $url = $this->completionUrl($token, $expiresAt);

        if ($notify) {
            Notification::route('mail', $registration->email)
                ->notify((new RegistrationLinkNotification($url, $this->linkLifetimeHours()))->locale($registration->locale ?? app()->getLocale()));
        }

        return $url;
    }

    /**
     * Status after the plan / payment step: proven e-mails skip the verification e-mail.
     */
    public function readyStatus(VendorRegistration $registration): RegistrationStatus
    {
        return $registration->isEmailVerified() ? RegistrationStatus::Incomplete : RegistrationStatus::EmailUnverified;
    }

    /**
     * The registration payment was captured (Billing PaymentCaptured): payment step done.
     * Idempotent; returns true when this call moved the registration on.
     */
    public function markPaid(VendorRegistration $registration, int $paymentLogId): bool
    {
        if (! in_array($registration->status, [RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed], true)) {
            return false;
        }

        $this->registrations->update($registration, [
            'status' => $this->readyStatus($registration),
            'meta' => [...($registration->meta ?? []), 'payment_log_id' => $paymentLogId],
        ]);

        return true;
    }

    public function markPaymentFailed(VendorRegistration $registration): void
    {
        if ($registration->status === RegistrationStatus::PaymentPending) {
            $this->registrations->update($registration, ['status' => RegistrationStatus::PaymentFailed]);
        }
    }

    /**
     * "Wrong e-mail? Change it" on the verification page: new address, new link.
     */
    public function changeEmail(VendorRegistration $registration, string $email): string
    {
        $this->assertOpen($registration);

        $this->registrations->update($registration, ['email' => Str::lower(trim($email)), 'email_verified_at' => null]);

        return $this->sendLink($registration);
    }

    public function completionUrl(string $token, Carbon $expiresAt): string
    {
        return URL::temporarySignedRoute('central.register.complete', $expiresAt, ['token' => $token]);
    }

    /**
     * Signed status page shown while the store is provisioned (WEB-020).
     */
    public function provisioningUrl(VendorRegistration $registration): string
    {
        return URL::temporarySignedRoute('central.register.provisioning', now()->addDay(), ['registration' => $registration->id]);
    }

    public function resolveToken(string $token): ?VendorRegistration
    {
        return $token === '' ? null : $this->registrations->findByTokenHash(hash('sha256', $token));
    }

    /**
     * First visit of the link proves the e-mail address.
     */
    public function open(VendorRegistration $registration): VendorRegistration
    {
        $this->assertOpen($registration);

        if ($registration->status === RegistrationStatus::EmailUnverified) {
            $this->registrations->update($registration, [
                'status' => RegistrationStatus::Incomplete,
                'email_verified_at' => now(),
            ]);
        }

        return $registration;
    }

    public function saveAccount(VendorRegistration $registration, RegistrationAccountData $data): VendorRegistration
    {
        $this->open($registration);

        $this->registrations->update($registration, [
            'owner_name' => $data->name,
            'password' => $data->password,
        ]);

        return $registration;
    }

    public function slugAvailable(string $slug, ?int $exceptRegistrationId = null): bool
    {
        $slug = Str::lower($slug);

        return TenantSlug::isValid($slug)
            && ! $this->tenants->slugExists($slug)
            && ! $this->registrations->slugReserved($slug, $exceptRegistrationId);
    }

    public function domainAvailable(string $host): bool
    {
        return ! $this->domainRequests->hostInUse(Str::lower($host));
    }

    public function complete(VendorRegistration $registration, RegistrationStoreData $data): Tenant
    {
        $this->assertOpen($registration);

        if ($registration->owner_name === null || ($registration->password === null && $registration->owner_id === null)) {
            throw PlatformRuleException::accountStepMissing();
        }

        return $this->transaction(function () use ($registration, $data): Tenant {
            $owner = $this->owners->forRegistration(
                $registration->email,
                $registration->owner_name,
                $registration->phone,
                $registration->getRawOriginal('password'),
                $registration->locale,
            );
            $paymentLogId = $registration->paymentLogId();

            $tenant = $this->provisioning->provision(new ProvisionTenantData(
                storeName: $data->storeName,
                slug: $data->slug,
                ownerName: $registration->owner_name,
                ownerEmail: $registration->email,
                ownerPhone: $registration->phone,
                ownerPasswordHash: $registration->getRawOriginal('password'),
                customDomain: $data->customDomain,
                defaultCountryId: is_numeric(($registration->meta ?? [])['default_country_id'] ?? null) ? (int) $registration->meta['default_country_id'] : null,
                profitPercentBps: $data->profitPercentBps,
                planId: $registration->plan_id,
                term: $registration->term,
                planAssignment: $paymentLogId !== null ? PlanAssignment::Prepaid : PlanAssignment::Trial,
                locale: $registration->locale,
                registrationId: $registration->id,
                ownerId: $owner->id,
                paymentLogId: $paymentLogId,
            ));

            $this->registrations->update($registration, [
                'store_name' => $data->storeName,
                'slug' => Str::lower($data->slug),
                'custom_domain' => $data->customDomain,
                'profit_percent_bps' => $data->profitPercentBps,
                'status' => RegistrationStatus::Completed,
                'completed_at' => now(),
                'tenant_id' => $tenant->id,
                'owner_id' => $owner->id,
                'password' => null,
            ]);

            $this->dispatchAfterCommit(new RegistrationCompleted($registration, $tenant));

            return $tenant;
        }, $this->connection());
    }

    /**
     * Signed JSON endpoint polled by the provisioning page (WEB-020).
     */
    public function provisioningStatusUrl(VendorRegistration $registration): string
    {
        return URL::temporarySignedRoute('central.register.provisioning.status', now()->addDay(), ['registration' => $registration->id]);
    }

    /**
     * Read model for the provisioning page (WEB-020): the store created by complete(),
     * or null while the registration has not reached the store step.
     */
    public function provisionedTenant(VendorRegistration $registration): ?Tenant
    {
        if ($registration->tenant_id === null) {
            return null;
        }

        /** @var Tenant|null $tenant */
        $tenant = $this->tenants->find($registration->tenant_id);

        return $tenant;
    }

    /**
     * Free alternatives for a taken store address (store step "Try megzkids or megz-store").
     *
     * @return list<string>
     */
    public function slugSuggestions(string $slug, ?int $exceptRegistrationId = null, int $limit = 2): array
    {
        $base = trim(Str::lower($slug), '-');
        $suggestions = [];

        if ($base === '') {
            return [];
        }

        foreach ([$base.'-store', $base.'-shop', $base.'shop', $base.'-online', $base.'-'.now()->format('y')] as $candidate) {
            if (count($suggestions) >= $limit) {
                break;
            }

            if ($this->slugAvailable($candidate, $exceptRegistrationId)) {
                $suggestions[] = $candidate;
            }
        }

        return $suggestions;
    }

    /**
     * Store step "Check DNS" (WEB-018a): the admin DNS templates rendered for a custom
     * domain that is not connected yet, each looked up live. Records that need the
     * per-store verification token are listed with found = null (the token is issued
     * once the store exists and shown in the store panel). Without templates the
     * default instruction is a CNAME from the domain to the store sub-domain.
     *
     * @return list<array{type: string, host: string, value: string, found: bool|null}>
     */
    public function dnsPreview(string $host, string $slug): array
    {
        $host = Str::lower(trim($host));
        $subdomain = TenantSlug::subdomainHost($slug);
        $templates = $this->dnsTemplates->ordered();
        $records = $templates->isEmpty()
            ? [['type' => DnsRecordType::CNAME->value, 'host' => '@', 'value' => '{subdomain}']]
            : $templates->map(fn (DnsRecordTemplate $template): array => ['type' => $template->type->value, 'host' => $template->host, 'value' => $template->value])->all();

        $rows = [];

        foreach ($records as $record) {
            $needsToken = str_contains($record['host'].$record['value'], '{token}');
            $replacements = ['{domain}' => $host, '{subdomain}' => $subdomain, '{token}' => ''];
            $recordHost = strtr($record['host'], $replacements);
            $value = strtr($record['value'], $replacements);
            $fqdn = match (true) {
                $recordHost === '' || $recordHost === '@' => $host,
                str_ends_with($recordHost, '.'.$host) || $recordHost === $host => $recordHost,
                default => $recordHost.'.'.$host,
            };
            $found = null;

            if (! $needsToken) {
                $expected = strtolower(rtrim($value, '.'));
                $found = in_array($expected, $this->dns->lookup($fqdn, DnsRecordType::from($record['type'])), true);
            }

            $rows[] = ['type' => $record['type'], 'host' => $recordHost === '' ? '@' : $recordHost, 'value' => $needsToken ? '' : $value, 'found' => $found];
        }

        return $rows;
    }

    public function delete(VendorRegistration $registration): void
    {
        if ($registration->status === RegistrationStatus::Completed) {
            throw PlatformRuleException::registrationClosed();
        }

        $this->registrations->delete($registration);
    }

    private function assertOpen(VendorRegistration $registration): void
    {
        if ($registration->status === RegistrationStatus::Completed || $registration->isExpired()) {
            throw PlatformRuleException::registrationClosed();
        }
    }

    private function connection(): ?string
    {
        return (new VendorRegistration)->getConnectionName();
    }
}
