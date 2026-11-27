<?php

namespace App\Modules\Platform\Legacy\Support;

use App\Modules\Platform\Legacy\Enums\LegacyWriteResult;
use BackedEnum;
use Closure;
use Illuminate\Database\Connection;

/**
 * Idempotent writes into one new database (central or a store) keyed by old ids.
 *
 * Every row goes through `upsert()`: mapped by a previous run → update; else matched by
 * a natural key (countries, currencies…) → map + update; else insert + map. The map
 * lives in `legacy_central_ids` / `legacy_store_ids` and is cached per entity (one
 * query per entity, no N+1). In a dry run nothing is written: new rows get negative
 * placeholder ids kept in memory so later steps still resolve their references.
 */
class LegacyWriter
{
    /** @var array<string, array<string, int>> entity => legacy key => new id */
    private array $map = [];

    private int $placeholder = 0;

    private ?bool $mapExists = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $mapTable,
        private readonly bool $dryRun,
        private readonly LegacyReport $report,
        private readonly string $scope,
    ) {}

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function dryRun(): bool
    {
        return $this->dryRun;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function id(string $entity, string|int|null $legacyKey): ?int
    {
        if ($legacyKey === null || $legacyKey === '') {
            return null;
        }

        $this->load($entity);

        return $this->map[$entity][(string) $legacyKey] ?? null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $match  natural key of a pre-existing row
     */
    public function upsert(string $entity, string|int $legacyKey, string $table, array $attributes, ?array $match = null): int
    {
        $attributes = $this->encode($attributes);
        $now = now();
        $hasTimestamps = ! array_key_exists('updated_at', $attributes) || $attributes['updated_at'] !== false;

        if ($hasTimestamps) {
            $attributes['updated_at'] = $attributes['updated_at'] ?? $now;
        } else {
            unset($attributes['updated_at']);
        }

        $id = $this->id($entity, $legacyKey);

        if ($id !== null && ($id < 0 || $this->connection->table($table)->where('id', $id)->exists())) {
            if (! $this->dryRun) {
                $this->connection->table($table)->where('id', $id)->update($this->withoutCreated($attributes));
            }

            $this->report->wrote($this->scope, $entity, LegacyWriteResult::Updated);

            return $id;
        }

        if ($match !== null) {
            $existing = $this->connection->table($table)->where($match)->value('id');

            if ($existing !== null) {
                if (! $this->dryRun) {
                    $this->connection->table($table)->where('id', $existing)->update($this->withoutCreated($attributes));
                }

                $this->remember($entity, $legacyKey, (int) $existing);
                $this->report->wrote($this->scope, $entity, LegacyWriteResult::Matched);

                return (int) $existing;
            }
        }

        if ($hasTimestamps) {
            $attributes['created_at'] = $attributes['created_at'] ?? $now;
        }

        $newId = $this->dryRun ? --$this->placeholder : (int) $this->connection->table($table)->insertGetId($attributes);
        $this->remember($entity, $legacyKey, $newId);
        $this->report->wrote($this->scope, $entity, LegacyWriteResult::Created);

        return $newId;
    }

    /**
     * Maps an old key to an existing new row without writing it (natural-key lookups).
     */
    public function link(string $entity, string|int $legacyKey, int $newId): void
    {
        if ($this->id($entity, $legacyKey) !== $newId) {
            $this->remember($entity, $legacyKey, $newId);
        }

        $this->report->wrote($this->scope, $entity, LegacyWriteResult::Matched);
    }

    /**
     * Replaces the pivot rows of one owner (`$column` = `$ownerId`).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncPivot(string $table, string $column, int $ownerId, array $rows): void
    {
        if ($this->dryRun) {
            return;
        }

        $this->connection->table($table)->where($column, $ownerId)->delete();

        foreach (array_chunk(array_map(fn (array $row): array => $this->encode($row), $rows), 500) as $chunk) {
            $this->connection->table($table)->insert($chunk);
        }
    }

    /**
     * One transaction per chunk (no-op wrapper in a dry run).
     */
    public function transaction(Closure $callback): void
    {
        if ($this->dryRun) {
            $callback();

            return;
        }

        $this->connection->transaction($callback);
    }

    public function forget(): void
    {
        $this->map = [];

        if (! $this->dryRun) {
            $this->connection->table($this->mapTable)->delete();
        }
    }

    private function load(string $entity): void
    {
        if (isset($this->map[$entity])) {
            return;
        }

        $this->map[$entity] = [];
        $this->mapExists ??= $this->connection->getSchemaBuilder()->hasTable($this->mapTable);

        if (! $this->mapExists) {
            return;
        }

        foreach ($this->connection->table($this->mapTable)->where('entity', $entity)->get(['legacy_key', 'new_id']) as $row) {
            $this->map[$entity][(string) $row->legacy_key] = (int) $row->new_id;
        }
    }

    private function remember(string $entity, string|int $legacyKey, int $newId): void
    {
        $this->load($entity);
        $this->map[$entity][(string) $legacyKey] = $newId;

        if ($this->dryRun || $newId < 0) {
            return;
        }

        $this->connection->table($this->mapTable)->upsert(
            [['entity' => $entity, 'legacy_key' => (string) $legacyKey, 'new_id' => $newId, 'created_at' => now(), 'updated_at' => now()]],
            ['entity', 'legacy_key'],
            ['new_id', 'updated_at'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function encode(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_array($value)) {
                $attributes[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($value instanceof BackedEnum) {
                $attributes[$key] = $value->value;
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withoutCreated(array $attributes): array
    {
        unset($attributes['created_at']);

        return $attributes;
    }
}
