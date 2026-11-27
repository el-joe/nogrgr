<?php

namespace App\Modules\Platform\Legacy\Support;

use App\Modules\Platform\Legacy\DTOs\LegacyRunOptions;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Connection;
use LogicException;

/**
 * Everything a step needs for one run: options, the old source, the report and the
 * id-mapping writers (central always; store while a store step runs).
 */
final class LegacyContext
{
    public ?LegacyWriter $store = null;

    public ?Connection $storeSource = null;

    public ?Tenant $tenant = null;

    public ?string $legacyTenantId = null;

    private ?LegacyTranslations $centralTranslations = null;

    private ?LegacyTranslations $storeTranslations = null;

    public function __construct(
        public readonly LegacyRunOptions $options,
        public readonly LegacySource $source,
        public readonly LegacyReport $report,
        public readonly LegacyWriter $central,
    ) {}

    public function dryRun(): bool
    {
        return $this->options->dryRun;
    }

    public function translations(): LegacyTranslations
    {
        return $this->centralTranslations ??= new LegacyTranslations($this->source->central());
    }

    public function storeTranslations(): LegacyTranslations
    {
        return $this->storeTranslations ??= new LegacyTranslations($this->storeSource ?? $this->source->central());
    }

    public function enterStore(string $legacyTenantId, ?Tenant $tenant, Connection $source, LegacyWriter $writer): void
    {
        $this->legacyTenantId = $legacyTenantId;
        $this->tenant = $tenant;
        $this->storeSource = $source;
        $this->store = $writer;
        $this->storeTranslations = null;
    }

    public function leaveStore(): void
    {
        $this->legacyTenantId = null;
        $this->tenant = null;
        $this->storeSource = null;
        $this->store = null;
        $this->storeTranslations = null;
    }

    public function storeWriter(): LegacyWriter
    {
        return $this->store ?? throw new LogicException('No store is being migrated.');
    }

    public function storeSource(): Connection
    {
        return $this->storeSource ?? throw new LogicException('No store is being migrated.');
    }

    /**
     * Report scope label: "central" or "store:<old id>".
     */
    public function scope(): string
    {
        return $this->legacyTenantId === null ? 'central' : 'store:'.$this->legacyTenantId;
    }
}
