<?php

namespace App\Modules\Platform\Legacy\DTOs;

use App\Shared\Money\Money;

/**
 * Result of `legacy:reconcile`: old vs new figures per store and for the platform, in
 * USD cents (null old value = not available on this server), plus old rows of stores
 * that were not migrated. Rendered as the "Finance reconciliation" report section.
 */
final class LegacyReconciliationData
{
    public const PLATFORM = 'Platform';

    /** @var list<array{scope: string, metric: string, old: int|null, new: int, money: bool}> */
    private array $lines = [];

    /** @var array<string, array{old: int|null, new: int}> */
    private array $totals = [];

    /** @var array<string, array{rows: int, amount: int}> */
    private array $excluded = [];

    /** @var list<string> */
    private array $notes = [];

    public function add(string $scope, string $metric, ?int $old, int $new, bool $money = true): void
    {
        $this->lines[] = ['scope' => $scope, 'metric' => $metric, 'old' => $old, 'new' => $new, 'money' => $money];
    }

    public function addTotal(string $metric, ?int $old, int $new): void
    {
        $current = $this->totals[$metric] ?? ['old' => 0, 'new' => 0];
        $this->totals[$metric] = [
            'old' => $current['old'] === null || $old === null ? null : $current['old'] + $old,
            'new' => $current['new'] + $new,
        ];
    }

    public function exclude(string $table, int $rows, int $amount): void
    {
        $this->excluded[$table] = ['rows' => $rows, 'amount' => $amount];
    }

    public function note(string $note): void
    {
        $this->notes[] = $note;
    }

    /**
     * Every comparable line, platform order totals included.
     *
     * @return list<array{scope: string, metric: string, old: int|null, new: int, money: bool}>
     */
    public function lines(): array
    {
        $platform = [];

        foreach ($this->totals as $metric => $total) {
            $platform[] = ['scope' => self::PLATFORM, 'metric' => $metric, 'old' => $total['old'], 'new' => $total['new'], 'money' => true];
        }

        return [...$this->lines, ...$platform];
    }

    public function line(string $scope, string $metric): ?array
    {
        foreach ($this->lines() as $line) {
            if ($line['scope'] === $scope && $line['metric'] === $metric) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Lines whose old value is known and differs from the new one.
     */
    public function differences(): int
    {
        return count(array_filter($this->lines(), static fn (array $line): bool => $line['old'] !== null && $line['old'] !== $line['new']));
    }

    /**
     * @return array<string, array{rows: int, amount: int}>
     */
    public function excluded(): array
    {
        return $this->excluded;
    }

    /**
     * @return list<string>
     */
    public function markdown(): array
    {
        $lines = ['## Finance reconciliation', '', 'Old vs new, USD. Diff must be 0 (n/a = old figure not available on this server).', ''];
        $lines[] = '| Scope | Metric | Old | New | Diff |';
        $lines[] = '|---|---|---:|---:|---:|';

        foreach ($this->lines() as $line) {
            $format = static fn (?int $value): string => $value === null ? 'n/a' : ($line['money'] ? Money::ofMinor($value)->format('en') : (string) $value);
            $diff = $line['old'] === null ? 'n/a' : $format($line['new'] - $line['old']);
            $lines[] = sprintf('| %s | %s | %s | %s | %s |', str_replace('|', '/', $line['scope']), $line['metric'], $format($line['old']), $format($line['new']), $diff);
        }

        $lines[] = '';
        $lines[] = '**Differences: '.$this->differences().'**';

        if ($this->excluded !== []) {
            $lines[] = '';
            $lines[] = 'Old rows of stores that are not in the new system (not compared):';
            $lines[] = '';

            foreach ($this->excluded as $table => $row) {
                $lines[] = sprintf('- %s: %d row(s), %s', $table, $row['rows'], Money::ofMinor($row['amount'])->format('en'));
            }
        }

        if ($this->notes !== []) {
            $lines[] = '';
        }

        foreach ($this->notes as $note) {
            $lines[] = '- '.$note;
        }

        return $lines;
    }
}
