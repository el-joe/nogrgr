<?php

namespace App\Modules\Platform\Legacy\Support;

use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyWriteResult;

/**
 * Counts and validation findings of one run, rendered to Markdown
 * (docs/plan/migration-report.md). Never holds secret values: callers pass ids and
 * messages only.
 */
class LegacyReport
{
    /** Findings listed per (scope, entity, message); the rest are only counted. */
    private const MAX_LISTED = 15;

    /** @var array<string, array<string, array<string, int>>> scope => entity => metric => n */
    private array $counts = [];

    /** @var array<string, array{level: LegacyIssueLevel, scope: string, entity: string, message: string, keys: list<string>, total: int}> */
    private array $issues = [];

    /** @var list<string> */
    private array $notes = [];

    /** @var list<list<string>> extra Markdown sections (finance reconciliation) */
    private array $sections = [];

    public function read(string $scope, string $entity, int $n = 1): void
    {
        $this->add($scope, $entity, 'read', $n);
    }

    public function wrote(string $scope, string $entity, LegacyWriteResult $result): void
    {
        $this->add($scope, $entity, $result->value, 1);
    }

    public function skipped(string $scope, string $entity, int $n = 1): void
    {
        $this->add($scope, $entity, 'skipped', $n);
    }

    public function issue(LegacyIssueLevel $level, string $scope, string $entity, string|int|null $legacyKey, string $message): void
    {
        $id = $level->value.'|'.$scope.'|'.$entity.'|'.$message;
        $this->issues[$id] ??= ['level' => $level, 'scope' => $scope, 'entity' => $entity, 'message' => $message, 'keys' => [], 'total' => 0];
        $this->issues[$id]['total']++;

        if ($legacyKey !== null && count($this->issues[$id]['keys']) < self::MAX_LISTED) {
            $this->issues[$id]['keys'][] = (string) $legacyKey;
        }
    }

    public function note(string $note): void
    {
        $this->notes[] = $note;
    }

    /**
     * @param  list<string>  $lines  Markdown lines starting with a `## ` heading
     */
    public function section(array $lines): void
    {
        $this->sections[] = $lines;
    }

    public function count(string $scope, string $entity, string $metric): int
    {
        return $this->counts[$scope][$entity][$metric] ?? 0;
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    public function errorCount(): int
    {
        return array_sum(array_map(static fn (array $issue): int => $issue['level'] === LegacyIssueLevel::Error ? $issue['total'] : 0, $this->issues));
    }

    /**
     * @return list<array{level: LegacyIssueLevel, scope: string, entity: string, message: string, keys: list<string>, total: int}>
     */
    public function issues(): array
    {
        return array_values($this->issues);
    }

    /**
     * @param  array<string, string>  $meta
     */
    public function toMarkdown(array $meta): string
    {
        $lines = ['# Legacy data migration report', ''];

        foreach ($meta as $label => $value) {
            $lines[] = "- **{$label}:** {$value}";
        }

        $lines[] = '';
        $lines[] = '## Counts';
        $lines[] = '';
        $lines[] = '| Scope | Entity | Read | Created | Updated | Matched | Skipped |';
        $lines[] = '|---|---|---:|---:|---:|---:|---:|';

        foreach ($this->counts as $scope => $entities) {
            foreach ($entities as $entity => $metrics) {
                $lines[] = sprintf('| %s | %s | %d | %d | %d | %d | %d |', $scope, $entity, $metrics['read'] ?? 0, $metrics['created'] ?? 0, $metrics['updated'] ?? 0, $metrics['matched'] ?? 0, $metrics['skipped'] ?? 0);
            }
        }

        $lines[] = '';
        $lines[] = '## Validation issues';
        $lines[] = '';

        if ($this->issues === []) {
            $lines[] = 'None.';
        } else {
            $lines[] = '| Level | Scope | Entity | Issue | Count | Old ids (first '.self::MAX_LISTED.') |';
            $lines[] = '|---|---|---|---|---:|---|';
            $issues = $this->issues();
            usort($issues, static fn (array $a, array $b): int => [$a['level']->value, $a['scope'], $a['entity']] <=> [$b['level']->value, $b['scope'], $b['entity']]);

            foreach ($issues as $issue) {
                $lines[] = sprintf('| %s | %s | %s | %s | %d | %s |', $issue['level']->value, $issue['scope'], $issue['entity'], str_replace('|', '/', $issue['message']), $issue['total'], implode(', ', $issue['keys']));
            }
        }

        if ($this->notes !== []) {
            $lines[] = '';
            $lines[] = '## Notes';
            $lines[] = '';

            foreach ($this->notes as $note) {
                $lines[] = '- '.$note;
            }
        }

        foreach ($this->sections as $section) {
            $lines[] = '';
            array_push($lines, ...$section);
        }

        return implode("\n", $lines)."\n";
    }

    private function add(string $scope, string $entity, string $metric, int $n): void
    {
        $this->counts[$scope][$entity][$metric] = ($this->counts[$scope][$entity][$metric] ?? 0) + $n;
    }
}
