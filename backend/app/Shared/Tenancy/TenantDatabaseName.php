<?php

namespace App\Shared\Tenancy;

use Illuminate\Support\Env;

final class TenantDatabaseName
{
    /**
     * nogrgr_tenant_{id}[.sqlite]                        normal runtime
     * nogrgr_tenant_{id}[_{TEST_DB_SUFFIX}]_testing[.sqlite]  tests (serial)
     * nogrgr_tenant_{id}_test_{token}[.sqlite]           tests (parallel worker {token})
     *
     * Test names never collide with real tenant databases on the same server, and
     * every test process gets its own tenant databases.
     */
    public static function for(int|string $tenantId): string
    {
        $name = config('tenancy.database.prefix').$tenantId;

        $token = TenancyServiceProvider::parallelToken();

        if ($token !== null) {
            $name .= '_test_'.$token;
        } elseif (app()->runningUnitTests()) {
            $suffix = Env::get('TEST_DB_SUFFIX');
            $name .= (is_string($suffix) && preg_match('/^[a-z0-9]{1,16}$/', $suffix) === 1 ? '_'.$suffix : '').'_testing';
        }

        $name .= config('tenancy.database.suffix');

        $template = (string) config('tenancy.database.template_tenant_connection');

        if (config("database.connections.{$template}.driver") === 'sqlite') {
            $name .= '.sqlite';
        }

        return $name;
    }
}
