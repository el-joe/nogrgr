<?php

namespace App\Modules\Engagement\Repositories\Contracts;

use App\Modules\Engagement\Models\SupportRequest;
use App\Shared\Repositories\Contracts\RepositoryContract;

/**
 * Storefront support requests (tenant DB).
 *
 * @extends RepositoryContract<SupportRequest>
 */
interface SupportRequestRepositoryContract extends RepositoryContract {}
