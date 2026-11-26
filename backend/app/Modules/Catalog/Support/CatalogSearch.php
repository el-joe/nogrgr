<?php

namespace App\Modules\Catalog\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Case-insensitive "name or sku contains" for catalog rows whose name is a translatable
 * JSON column. MySQL compares JSON as binary (case-sensitive), so the name is matched on
 * the lower-cased values of every locale (`$.*`), never on the JSON keys. SQLite (CI leg)
 * stores JSON as text and LIKE is already case-insensitive for ASCII.
 */
final class CatalogSearch
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function nameOrSku(Builder $query, string $term, string $nameColumn = 'name', string $skuColumn = 'sku'): Builder
    {
        $like = '%'.addcslashes(mb_strtolower(trim($term)), '%_\\').'%';
        $grammar = $query->getQuery()->getGrammar();
        $name = $grammar->wrap($nameColumn);
        $sku = $grammar->wrap($skuColumn);
        $driver = $query->getModel()->getConnection()->getDriverName();

        $nameSql = in_array($driver, ['mysql', 'mariadb'], true)
            ? "LOWER(JSON_UNQUOTE(JSON_EXTRACT({$name}, '$.*'))) LIKE ?"
            : "LOWER({$name}) LIKE ?";

        return $query->where(static fn (Builder $inner) => $inner
            ->whereRaw($nameSql, [$like])
            ->orWhereRaw("LOWER({$sku}) LIKE ?", [$like]));
    }
}
