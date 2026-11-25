<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Contracts\CertificateInspector;
use App\Modules\Platform\Contracts\DnsResolver;
use App\Modules\Platform\Events\ComplianceReviewed;
use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Platform\Events\TenantProvisioningFailed;
use App\Modules\Platform\Listeners\AlertProvisioningFailure;
use App\Modules\Platform\Listeners\NotifyOwnerOfComplianceDecision;
use App\Modules\Platform\Listeners\WelcomeProvisionedVendor;
use App\Modules\Platform\Repositories\Contracts\AuditEventRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Modules\Platform\Repositories\Eloquent\AuditEventRepository;
use App\Modules\Platform\Repositories\Eloquent\ComplianceProfileRepository;
use App\Modules\Platform\Repositories\Eloquent\DnsRecordTemplateRepository;
use App\Modules\Platform\Repositories\Eloquent\DomainRequestRepository;
use App\Modules\Platform\Repositories\Eloquent\TenantRepository;
use App\Modules\Platform\Repositories\Eloquent\VendorRegistrationRepository;
use App\Modules\Platform\Support\StreamCertificateInspector;
use App\Modules\Platform\Support\SystemDnsResolver;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Support\Facades\Event;

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
        DnsRecordTemplateRepositoryContract::class => DnsRecordTemplateRepository::class,
        DnsResolver::class => SystemDnsResolver::class,
        CertificateInspector::class => StreamCertificateInspector::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(TenantProvisioned::class, WelcomeProvisionedVendor::class);
        Event::listen(TenantProvisioningFailed::class, AlertProvisioningFailure::class);
        Event::listen(ComplianceReviewed::class, NotifyOwnerOfComplianceDecision::class);
    }
}
