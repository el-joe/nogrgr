<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\DTOs\AuditEntryData;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Repositories\Contracts\AuditEventRepositoryContract;
use App\Shared\Routing\VendorPanel;
use App\Shared\Services\BaseService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Records who did what, where: actor + guard, tenant, action, subject, changes, ip, user agent.
 */
class Auditor extends BaseService
{
    /**
     * Guards checked (in order) to resolve the acting user. Tenant guards are only
     * consulted in tenant context (their users live in the tenant DB).
     */
    public const CENTRAL_GUARDS = ['admin', 'affiliate'];

    public const TENANT_GUARDS = ['vendor', 'customer', 'sanctum'];

    /**
     * Attribute names never written to the audit trail.
     */
    public const REDACTED = ['password', 'password_confirmation', 'remember_token', 'token', 'secret', 'api_key', 'current_password'];

    public function __construct(
        private readonly AuditEventRepositoryContract $events,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(AuditAction|string $action, ?Model $subject = null, array $changes = []): AuditEvent
    {
        return $this->recordEntry(new AuditEntryData(
            action: $action instanceof AuditAction ? $action->value : $action,
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject?->getKey() !== null ? (string) $subject->getKey() : null,
            changes: $changes === [] ? null : $this->redact($changes),
        ));
    }

    public function recordEntry(AuditEntryData $entry): AuditEvent
    {
        [$guard, $actor] = $this->resolveActor();
        $changes = $entry->changes;
        $impersonator = $this->request->hasSession() ? $this->request->session()->get(VendorPanel::SESSION_IMPERSONATOR) : null;

        if ($guard === 'vendor' && $impersonator !== null && ! isset($changes['impersonator_admin_id'])) {
            $changes = ['impersonator_admin_id' => $impersonator] + ($changes ?? []);
        }

        /** @var AuditEvent $event */
        $event = $this->events->create([
            'tenant_id' => tenancy()->initialized ? tenant()?->getTenantKey() : null,
            'guard' => $guard,
            'actor_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
            'actor_id' => $actor?->getAuthIdentifier(),
            'action' => Str::limit($entry->action, 191, ''),
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'changes' => $changes,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 512, ''),
            'url' => Str::limit($this->request->fullUrl(), 2048, ''),
        ]);

        return $event;
    }

    /**
     * @return array{0: string|null, 1: Authenticatable|null}
     */
    public function resolveActor(): array
    {
        $guards = tenancy()->initialized
            ? [...self::TENANT_GUARDS, ...self::CENTRAL_GUARDS]
            : self::CENTRAL_GUARDS;

        foreach ($guards as $guard) {
            if (! array_key_exists($guard, (array) config('auth.guards'))) {
                continue;
            }

            if ($guard === 'sanctum' && ! $this->request->bearerToken()) {
                continue;
            }

            if ($guard === 'vendor' && ! $this->vendorSessionBelongsToCurrentTenant()) {
                continue;
            }

            $user = Auth::guard($guard)->user();

            if ($user !== null) {
                return [$guard, $user];
            }
        }

        return [null, null];
    }

    /**
     * A vendor session is bound to one tenant (VendorPanel::SESSION_TENANT); never resolve
     * its user id inside another tenant's database.
     */
    private function vendorSessionBelongsToCurrentTenant(): bool
    {
        $guard = Auth::guard('vendor');

        if ($guard instanceof SessionGuard && $guard->hasUser()) {
            return true;
        }

        if (! $this->request->hasSession()) {
            return false;
        }

        return (string) $this->request->session()->get(VendorPanel::SESSION_TENANT) === (string) tenant()?->getTenantKey();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
