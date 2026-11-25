<?php

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Central DB.
 *
 * @extends RepositoryContract<DnsRecordTemplate>
 */
interface DnsRecordTemplateRepositoryContract extends RepositoryContract
{
    /**
     * @return Collection<int, DnsRecordTemplate>
     */
    public function ordered(): Collection;
}
