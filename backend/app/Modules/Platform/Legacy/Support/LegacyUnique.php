<?php

namespace App\Modules\Platform\Legacy\Support;

use Illuminate\Support\Str;

/**
 * Keeps unique columns (slug, sku, code) unique when old data collides: the candidate
 * is kept when free (or already ours), else suffixed with the old id.
 */
final class LegacyUnique
{
    public static function value(LegacyWriter $writer, string $table, string $column, string $candidate, ?int $ownId, string|int $legacyKey, int $max = 191): string
    {
        $candidate = Str::limit($candidate, $max, '');
        $query = $writer->connection()->table($table)->where($column, $candidate);

        if ($ownId !== null && $ownId > 0) {
            $query->where('id', '!=', $ownId);
        }

        if (! $query->exists()) {
            return $candidate;
        }

        $suffix = '-'.$legacyKey;

        return Str::limit($candidate, $max - strlen($suffix), '').$suffix;
    }
}
