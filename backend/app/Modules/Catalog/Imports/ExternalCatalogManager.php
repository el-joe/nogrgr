<?php

namespace App\Modules\Catalog\Imports;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Imports\Contracts\ExternalCatalogDriver;
use App\Modules\Catalog\Imports\Drivers\NeozenaDriver;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the driver implementation of an ImportDriver case.
 */
class ExternalCatalogManager
{
    public function __construct(private readonly Container $container) {}

    public function driver(ImportDriver $driver): ExternalCatalogDriver
    {
        return match ($driver) {
            ImportDriver::Neozena => $this->container->make(NeozenaDriver::class),
        };
    }
}
