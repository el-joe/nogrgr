<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Models\Requests\ProductRequest;

/**
 * @extends ThreadedRequestRepositoryContract<ProductRequest>
 */
interface ProductRequestRepositoryContract extends ThreadedRequestRepositoryContract {}
