<?php

namespace App\Modules\Platform;

use App\Modules\Billing\Events\PaymentCaptured;
use App\Modules\Billing\Events\PaymentFailed;
use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Contracts\CertificateInspector;
use App\Modules\Platform\Contracts\DnsResolver;
use App\Modules\Platform\Events\ComplianceReviewed;
use App\Modules\Platform\Events\ComplianceSubmitted;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Events\TenantProvisioningFailed;
use App\Modules\Platform\Legacy\Console\LegacyMigrateCommand;
use App\Modules\Platform\Legacy\Console\LegacyReconcileCommand;
use App\Modules\Platform\Listeners\AdvanceRegistrationOnPayment;
use App\Modules\Platform\Listeners\AlertProvisioningFailure;
use App\Modules\Platform\Listeners\NotifyAdminsOfComplianceSubmission;
use App\Modules\Platform\Listeners\NotifyOwnerOfComplianceDecision;
use App\Modules\Platform\Listeners\WelcomeProvisionedVendor;
use App\Modules\Platform\Policies\OwnerStorePolicy;
use App\Modules\Platform\Repositories\Contracts\AuditEventRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Repositories\Eloquent\AuditEventRepository;
use App\Modules\Platform\Repositories\Eloquent\ComplianceProfileRepository;
use App\Modules\Platform\Repositories\Eloquent\DnsRecordTemplateRepository;
use App\Modules\Platform\Repositories\Eloquent\DomainRequestRepository;
use App\Modules\Platform\Repositories\Eloquent\StoreSettingRepository;
use App\Modules\Platform\Repositories\Eloquent\TenantRepository;
use App\Modules\Platform\Repositories\Eloquent\VendorRegistrationRepository;
use App\Modules\Platform\Support\PartialMigrationCleaner;
use App\Modules\Platform\Support\SetupBadgeCounter;
use App\Modules\Platform\Support\StreamCertificateInspector;
use App\Modules\Platform\Support\SystemDnsResolver;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Database\Events\MigrationFailed;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        AuditEventRepositoryContract::class => AuditEventRepository::class,
        TenantRepositoryContract::class => TenantRepository::class,
        VendorRegistrationRepositoryContract::class => VendorRegistrationRepository::class,
        ComplianceProfileRepositoryContract::class => ComplianceProfileRepository::class,
        DomainRequestRepositoryContract::class => DomainRequestRepository::class,
        StoreSettingRepositoryContract::class => StoreSettingRepository::class,
        DnsRecordTemplateRepositoryContract::class => DnsRecordTemplateRepository::class,
        DnsResolver::class => SystemDnsResolver::class,
        CertificateInspector::class => StreamCertificateInspector::class,
    ];

    /**
     * @var array<class-string, class-string>
     */
    public $singletons = [
        PartialMigrationCleaner::class => PartialMigrationCleaner::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->tag([SetupBadgeCounter::class], PanelBadgeCounter::TAG);
        Blade::anonymousComponentPath(__DIR__.'/resources/views/components/vendor', 'vendor');

        if ($this->app->runningInConsole()) {
            $this->commands([LegacyMigrateCommand::class, LegacyReconcileCommand::class]);
        }

        Event::listen(MigrationStarted::class, [PartialMigrationCleaner::class, 'started']);
        Event::listen(MigrationFailed::class, [PartialMigrationCleaner::class, 'failed']);
        Event::listen(TenantProvisioned::class, WelcomeProvisionedVendor::class);
        Event::listen(TenantProvisioningFailed::class, AlertProvisioningFailure::class);
        Event::listen(ComplianceReviewed::class, NotifyOwnerOfComplianceDecision::class);
        Event::listen(ComplianceSubmitted::class, NotifyAdminsOfComplianceSubmission::class);
        Event::listen(PaymentCaptured::class, [AdvanceRegistrationOnPayment::class, 'captured']);
        Event::listen(PaymentFailed::class, [AdvanceRegistrationOnPayment::class, 'failed']);

        // Owner portal: an active owner may add stores (one owner, many stores).
        Gate::define('owner.stores.create', static fn (mixed $user): bool => $user instanceof Owner && $user->isActive());
        Gate::define('owner.portal', [OwnerStorePolicy::class, 'portal']);
        Gate::define('owner.stores.manage', [OwnerStorePolicy::class, 'manageStore']);
        Gate::define('owner.domains.manage', [OwnerStorePolicy::class, 'manageDomain']);
    }
}
