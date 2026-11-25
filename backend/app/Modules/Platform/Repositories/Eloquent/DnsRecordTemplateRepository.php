<?php

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<DnsRecordTemplate>
 */
class DnsRecordTemplateRepository extends BaseRepository implements DnsRecordTemplateRepositoryContract
{
    protected function model(): string
    {
        return DnsRecordTemplate::class;
    }

    public function ordered(): Collection
    {
        return $this->query()->orderBy('sort_order')->orderBy('id')->get();
    }
}
