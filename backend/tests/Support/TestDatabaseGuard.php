<?php

namespace Tests\Support;

/**
 * Hard stop against running tests (RefreshDatabase = migrate:fresh, tenant DB drops)
 * on a non-test database such as the `nogrgr` dev DB or a real nogrgr_tenant_{id}.
 *
 * Safe names: ":memory:", anything ending in "_testing", anything containing "_test_"
 * (Laravel's parallel suffix). For SQLite file paths the basename is checked.
 */
final class TestDatabaseGuard
{
    public static function isSafeName(?string $database): bool
    {
        if ($database === null || $database === '') {
            return false;
        }

        if ($database === ':memory:') {
            return true;
        }

        $name = preg_replace('/\.sqlite$/', '', basename($database)) ?? $database;

        return str_ends_with($name, '_testing') || str_contains($name, '_test_');
    }

    public static function assertSafeName(?string $database, string $what): void
    {
        if (self::isSafeName($database)) {
            return;
        }

        $message = sprintf(
            '[tests] ABORTED: refusing to use %s "%s". Test databases must end with "_testing" or contain "_test_" '
            .'(phpunit.xml forces DB_DATABASE=nogrgr_testing).',
            $what,
            (string) $database,
        );

        fwrite(STDERR, PHP_EOL.$message.PHP_EOL);

        exit(1);
    }
}
