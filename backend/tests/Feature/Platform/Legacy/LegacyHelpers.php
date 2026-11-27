<?php

use App\Modules\Platform\Legacy\DTOs\LegacyRunOptions;
use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use App\Modules\Platform\Legacy\Services\LegacyMigrationService;
use App\Modules\Platform\Legacy\Support\LegacyReport;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\ParallelTesting;

/*
| Shared by the legacy:migrate tests: a fixture copy of the OLD schema
| (tests/Fixtures/legacy) created per parallel worker in two throw-away databases.
*/

function legacyFixture(): object
{
    $token = ParallelTesting::token() ?: '0';
    $central = 'legacy_fixture_test_'.$token;
    $store = 'legacy_store_fixture_test_'.$token;
    $config = config('database.connections.'.config('database.default'));
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']), $config['username'], $config['password']);
    $oldKey = Encrypter::generateKey('AES-256-CBC');
    $old = new Encrypter($oldKey, 'AES-256-CBC');
    $dir = base_path('tests/Fixtures/legacy');

    foreach ([$central => ['central-schema.sql', 'central-data.sql'], $store => ['store-schema.sql', 'store-data.sql']] as $database => $files) {
        $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
        $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$database}`");

        foreach ($files as $file) {
            $sql = strtr((string) file_get_contents($dir.'/'.$file), [
                '{{STORE_DB}}' => $store,
                '{{MAIL_PASSWORD}}' => $old->encrypt('legacy-mail-secret', false),
                '{{STRIPE_CREDENTIALS}}' => $old->encrypt(json_encode(['key' => 'pk_test_legacy', 'secret' => 'sk_test_legacy', 'sandbox' => '1']), false),
            ]);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0; '.$sql);
        }
    }

    test()->beforeApplicationDestroyed(static function () use ($pdo, $central, $store): void {
        $pdo->exec("DROP DATABASE IF EXISTS `{$central}`");
        $pdo->exec("DROP DATABASE IF EXISTS `{$store}`");
    });

    return (object) [
        'config' => new LegacySourceConfig($config['host'], (int) $config['port'], $central, $config['username'], (string) $config['password'], 'base64:'.base64_encode($oldKey), $dir.'/storage'),
    ];
}

function runLegacy(object $fixture, bool $dryRun = false, array $steps = []): LegacyReport
{
    return app(LegacyMigrationService::class)->run($fixture->config, new LegacyRunOptions(
        steps: $steps,
        dryRun: $dryRun,
    ));
}

function legacyTenant(): Tenant
{
    return Tenant::query()->where('slug', 'legacystore')->firstOrFail();
}
