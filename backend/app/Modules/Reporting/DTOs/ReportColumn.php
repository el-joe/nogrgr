<?php

namespace App\Modules\Reporting\DTOs;

use Closure;

/**
 * One column of a report table: the DataTable cell and the CSV value come from the same
 * raw value (`$row->{key}` or the `value` closure), formatted by `type`:
 * text · money (USD minor) · number · decimal (one decimal) · percent · month (`Y-m`).
 * `secondary` adds a muted second line in the table (e.g. the store slug under its name).
 */
final readonly class ReportColumn
{
    /**
     * @param  (Closure(object): (int|float|string|null))|null  $value
     * @param  (Closure(object): (string|null))|null  $secondary
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
        public bool $sortable = true,
        public ?Closure $value = null,
        public ?Closure $secondary = null,
    ) {}

    /**
     * Store column (name + slug) of rows selecting `tenant_name` / `tenant_slug`.
     */
    public static function store(string $label): self
    {
        return new self('tenant_name', $label, 'text', true, null, static fn (object $row): ?string => isset($row->tenant_slug) ? (string) $row->tenant_slug : null);
    }

    public function raw(object $row): int|float|string|null
    {
        if ($this->value !== null) {
            return ($this->value)($row);
        }

        $value = $row->{$this->key} ?? null;

        return is_numeric($value) && in_array($this->type, ['money', 'number'], true) ? (int) $value : $value;
    }

    public function alignEnd(): bool
    {
        return in_array($this->type, ['money', 'number', 'decimal', 'percent'], true);
    }
}
