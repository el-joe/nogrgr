<?php

/*
|--------------------------------------------------------------------------
| Test bootstrap (runs in every PHPUnit / paratest worker, after phpunit.xml <env>)
|--------------------------------------------------------------------------
|
| 1. Optional explicit override of the forced test database:
|      TEST_DB_CONNECTION=sqlite TEST_DB_DATABASE=:memory: php artisan test --parallel
| 2. Refuses unsafe database names (see Tests\Support\TestDatabaseGuard).
| 3. Creates the MySQL test database (default nogrgr_testing) if it is missing, using
|    the host/credentials from the real environment or .env. Parallel workers derive
|    nogrgr_testing_test_{unique-token} from it (created by Laravel).
| 4. Serial runs take a MySQL advisory lock so two serial runs on one machine queue up
|    instead of migrating the same nogrgr_testing at once.
| 5. Parallel workers drop their own central + tenant test databases on exit, so no
|    test databases are left behind.
|
*/

use Dotenv\Dotenv;
use Tests\Support\TestDatabaseGuard;

require __DIR__.'/../vendor/autoload.php';

$setEnv = static function (string $key, string $value): void {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
};

$override = getenv('TEST_DB_CONNECTION');

if (is_string($override) && $override !== '') {
    $setEnv('DB_CONNECTION', $override);
    $setEnv('DB_DATABASE', (string) (getenv('TEST_DB_DATABASE') ?: ($override === 'sqlite' ? ':memory:' : 'nogrgr_testing')));
}

/*
 * Optional run-specific suffix so concurrent *serial* runs (several agents on one machine)
 * get separate databases: TEST_DB_SUFFIX=fe → nogrgr_fe_testing, tenants nogrgr_tenant_{id}_fe_testing.
 * Parallel runs are already isolated by paratest's unique worker token.
 */
$suffix = getenv('TEST_DB_SUFFIX');

if (is_string($suffix) && $suffix !== '') {
    if (preg_match('/^[a-z0-9]{1,16}$/', $suffix) !== 1) {
        fwrite(STDERR, PHP_EOL.'[tests] TEST_DB_SUFFIX must be 1-16 lowercase letters/digits.'.PHP_EOL);
        exit(1);
    }

    $current = (string) getenv('DB_DATABASE');

    if (str_ends_with($current, '_testing')) {
        $setEnv('DB_DATABASE', substr($current, 0, -strlen('_testing')).'_'.$suffix.'_testing');
    }
}

$connection = (string) getenv('DB_CONNECTION');
$database = (string) getenv('DB_DATABASE');

TestDatabaseGuard::assertSafeName($database, 'central test database (DB_DATABASE)');

if (in_array($connection, ['mysql', 'mariadb'], true)) {
    $dotenv = is_file(__DIR__.'/../.env') ? Dotenv::parse((string) file_get_contents(__DIR__.'/../.env')) : [];

    $value = static function (string $key, string $default) use ($dotenv): string {
        $env = getenv($key);

        return is_string($env) && $env !== '' ? $env : (string) ($dotenv[$key] ?? $default);
    };

    $socket = $value('DB_SOCKET', '');
    $dsn = $socket !== ''
        ? "mysql:unix_socket={$socket}"
        : sprintf('mysql:host=%s;port=%s', $value('DB_HOST', '127.0.0.1'), $value('DB_PORT', '3306'));

    try {
        $pdo = new PDO($dsn, $value('DB_USERNAME', 'root'), $value('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '', $database),
        ));

        $uniqueToken = $_SERVER['UNIQUE_TEST_TOKEN'] ?? getenv('UNIQUE_TEST_TOKEN');

        if (is_string($uniqueToken) && preg_match('/^[A-Za-z0-9_]+$/', $uniqueToken) === 1) {
            register_shutdown_function(static function () use ($pdo, $database, $uniqueToken): void {
                $suffix = '_test_'.$uniqueToken;
                $names = [$database.$suffix];

                $like = str_replace('_', '\\_', 'nogrgr_tenant_').'%'.str_replace('_', '\\_', $suffix);

                foreach ($pdo->query('SHOW DATABASES LIKE '.$pdo->quote($like)) ?: [] as $row) {
                    $names[] = (string) $row[0];
                }

                foreach ($names as $name) {
                    if (TestDatabaseGuard::isSafeName($name) && str_contains($name, $suffix)) {
                        $pdo->exec('DROP DATABASE IF EXISTS `'.str_replace('`', '', $name).'`');
                    }
                }
            });
        } elseif (getenv('PARATEST') === false
            && getenv('LARAVEL_PARALLEL_TESTING') === false
            && array_intersect($_SERVER['argv'] ?? [], ['--parallel', '-p']) === []) {
            $locked = (int) $pdo->query('SELECT GET_LOCK('.$pdo->quote('nogrgr-tests:'.$database).', 900)')->fetchColumn();

            if ($locked !== 1) {
                fwrite(STDERR, PHP_EOL."[tests] Another serial test run is using `{$database}`; gave up after 15 minutes.".PHP_EOL);
                exit(1);
            }

            $GLOBALS['__nogrgrTestLockConnection'] = $pdo;
        }
    } catch (PDOException $exception) {
        fwrite(STDERR, PHP_EOL."[tests] Cannot prepare the MySQL test database `{$database}`: {$exception->getMessage()}".PHP_EOL
            .'        Check DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD in .env, or run with TEST_DB_CONNECTION=sqlite.'.PHP_EOL);
        exit(1);
    }
}
