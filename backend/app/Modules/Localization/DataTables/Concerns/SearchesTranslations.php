<?php

namespace App\Modules\Localization\DataTables\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Case-insensitive LIKE on the en / ar values of a translatable JSON column
 * (JSON strings compare with a binary collation, so both sides are lower-cased).
 */
trait SearchesTranslations
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function orWhereTranslationLike(Builder $query, string $column, string $term): void
    {
        foreach (['en', 'ar'] as $locale) {
            $query->orWhereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`'.$column.'`, ?))) LIKE ?', ['$.'.$locale, mb_strtolower($term)]);
        }
    }
}
