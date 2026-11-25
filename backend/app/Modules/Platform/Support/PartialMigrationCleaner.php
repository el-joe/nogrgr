<?php

namespace App\Modules\Platform\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationFailed;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Makes a store-database migration step atomic on engines without transactional DDL
 * (MySQL): it notes the tables that exist when a migration's up() starts and, if the
 * migration then fails, drops the tables that migration had already created. The
 * failed migration is not recorded in `migrations`, so a retry resumes exactly there
 * on a clean schema instead of hitting "Table ... already exists".
 *
 * Only active while TenantDatabaseProvisioner runs (armed/disarmed around it), so the
 * central `migrate` command is never touched.
 */
class PartialMigrationCleaner
{
    private bool $armed = false;

    /** @var list<string>|null */
    private ?array $tablesBefore = null;

    public function arm(): void
    {
        $this->armed = true;
        $this->tablesBefore = null;
    }

    public function disarm(): void
    {
        $this->armed = false;
        $this->tablesBefore = null;
    }

    public function started(MigrationStarted $event): void
    {
        if (! $this->armed || $event->method !== 'up') {
            return;
        }

        $this->tablesBefore = $this->tables($this->connection($event->migration->getConnection()));
    }

    public function failed(MigrationFailed $event): void
    {
        if (! $this->armed || $event->method !== 'up' || $this->tablesBefore === null) {
            return;
        }

        $connection = $this->connection($event->migration->getConnection());
        $created = array_values(array_diff($this->tables($connection), $this->tablesBefore));
        $this->tablesBefore = null;

        if ($created === []) {
            return;
        }

        $schema = $connection->getSchemaBuilder();

        try {
            $schema->withoutForeignKeyConstraints(static function () use ($schema, $created): void {
                foreach ($created as $table) {
                    $schema->dropIfExists($table);
                }
            });
        } catch (Throwable $exception) {
            // The original migration error is what the operator needs; this one is extra context.
            report($exception);
        }
    }

    private function connection(?string $name): Connection
    {
        return DB::connection($name);
    }

    /**
     * Tables of the connection's own database only (never another store's DB).
     *
     * @return list<string>
     */
    private function tables(Connection $connection): array
    {
        $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;

        return array_values($connection->getSchemaBuilder()->getTableListing($schema, false));
    }
}
