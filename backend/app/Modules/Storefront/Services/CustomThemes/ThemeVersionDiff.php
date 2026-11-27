<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Storefront\Models\CustomThemeVersion;

/**
 * File and line diff of a version against its base (the live / previous approved version)
 * for the review detail (ADM-089a): added / removed / changed files from the sha256 list,
 * and a line diff (LCS) of changed text files up to MAX_LINES lines each.
 */
class ThemeVersionDiff
{
    public const MAX_LINES = 600;

    private const TEXT = ['twig', 'json', 'css', 'js', 'svg', 'md', 'txt'];

    public function __construct(private readonly CustomThemePackageStore $store) {}

    /**
     * @return array{base: CustomThemeVersion|null, added: list<string>, removed: list<string>, changed: array<string, list<array{type: string, text: string}>|null>, unchanged: int}
     */
    public function compare(CustomThemeVersion $version, ?CustomThemeVersion $base): array
    {
        $new = $version->files();
        $old = $base?->files() ?? [];
        $added = array_values(array_diff(array_keys($new), array_keys($old)));
        $removed = $base === null ? [] : array_values(array_diff(array_keys($old), array_keys($new)));
        $changed = [];
        $unchanged = 0;

        if ($base === null) {
            return ['base' => null, 'added' => $added, 'removed' => [], 'changed' => [], 'unchanged' => 0];
        }

        $newContents = $this->store->contents($version, true);
        $oldContents = $this->store->contents($base, true);

        foreach (array_intersect(array_keys($new), array_keys($old)) as $path) {
            if ($new[$path]['sha256'] === $old[$path]['sha256']) {
                $unchanged++;

                continue;
            }

            $changed[$path] = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TEXT, true)
                ? $this->lines((string) ($oldContents[$path] ?? ''), (string) ($newContents[$path] ?? ''))
                : null;
        }

        return ['base' => $base, 'added' => $added, 'removed' => $removed, 'changed' => $changed, 'unchanged' => $unchanged];
    }

    /**
     * Changed lines with 2 lines of context ("add" / "del" / "same" / "gap"); null when too large.
     *
     * @return list<array{type: string, text: string}>|null
     */
    public function lines(string $before, string $after): ?array
    {
        $a = explode("\n", str_replace("\r\n", "\n", $before));
        $b = explode("\n", str_replace("\r\n", "\n", $after));
        $n = count($a);
        $m = count($b);

        if ($n > self::MAX_LINES || $m > self::MAX_LINES) {
            return null;
        }

        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $ops[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($j < $m && ($i >= $n || $lcs[$i][$j + 1] >= $lcs[$i + 1][$j])) {
                $ops[] = ['type' => 'add', 'text' => $b[$j]];
                $j++;
            } else {
                $ops[] = ['type' => 'del', 'text' => $a[$i]];
                $i++;
            }
        }

        $keep = [];

        foreach ($ops as $index => $op) {
            if ($op['type'] !== 'same') {
                for ($k = max(0, $index - 2); $k <= min(count($ops) - 1, $index + 2); $k++) {
                    $keep[$k] = true;
                }
            }
        }

        $out = [];
        $last = -1;

        foreach ($ops as $index => $op) {
            if (! isset($keep[$index])) {
                continue;
            }

            if ($last !== -1 && $index > $last + 1) {
                $out[] = ['type' => 'gap', 'text' => '…'];
            }

            $out[] = $op;
            $last = $index;
        }

        return $out;
    }
}
