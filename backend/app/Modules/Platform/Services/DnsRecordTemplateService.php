<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\DTOs\DnsRecordTemplateData;
use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Platform DNS record templates shown to vendors for custom domains.
 */
class DnsRecordTemplateService extends BaseService
{
    public function __construct(private readonly DnsRecordTemplateRepositoryContract $templates) {}

    /**
     * @return Collection<int, DnsRecordTemplate>
     */
    public function ordered(): Collection
    {
        return $this->templates->ordered();
    }

    /**
     * The records as a vendor sees them, rendered for an example store
     * ("Shown to vendors as" card on the DNS templates tab).
     *
     * @return list<array{type: string, host: string, value: string, ttl: int, priority: int|null}>
     */
    public function preview(string $exampleSlug = 'yourstore', string $exampleDomain = 'yourstore.com'): array
    {
        $replacements = [
            '{domain}' => $exampleDomain,
            '{subdomain}' => TenantSlug::subdomainHost($exampleSlug),
            '{token}' => '8f2a5d07c91',
        ];

        return $this->templates->ordered()
            ->map(fn (DnsRecordTemplate $template): array => $template->render($replacements))
            ->values()
            ->all();
    }

    public function create(DnsRecordTemplateData $data): DnsRecordTemplate
    {
        /** @var DnsRecordTemplate $template */
        $template = $this->templates->create($this->attributes($data));

        return $template;
    }

    public function update(DnsRecordTemplate $template, DnsRecordTemplateData $data): DnsRecordTemplate
    {
        /** @var DnsRecordTemplate $template */
        $template = $this->templates->update($template, $this->attributes($data));

        return $template;
    }

    public function delete(DnsRecordTemplate $template): void
    {
        $this->templates->delete($template);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(DnsRecordTemplateData $data): array
    {
        return [
            'type' => $data->type,
            'host' => trim($data->host),
            'value' => trim($data->value),
            'ttl' => $data->ttl,
            'priority' => $data->type->requiresPriority() ? $data->priority : null,
            'sort_order' => $data->sortOrder,
        ];
    }
}
