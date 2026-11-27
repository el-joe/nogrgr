<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The storefront product listings (Figma 1:1191 + variant notes 137:113051): one
 * template, these entry points; each has its own default sort, filters and empty state.
 */
enum ListingKind: string
{
    use HasEnumHelpers;

    case BestSelling = 'best_selling';
    case NewIn = 'new_in';
    case TopRated = 'top_rated';
    case Offers = 'offers';
    case Category = 'category';
    case Search = 'search';

    public function defaultSort(): ListingSort
    {
        return match ($this) {
            self::BestSelling => ListingSort::BestSelling,
            self::NewIn => ListingSort::Newest,
            self::TopRated => ListingSort::Rating,
            self::Offers => ListingSort::Discount,
            self::Category => ListingSort::Recommended,
            self::Search => ListingSort::Relevance,
        };
    }

    /**
     * @return list<ListingSort>
     */
    public function sorts(): array
    {
        $common = [ListingSort::BestSelling, ListingSort::Newest, ListingSort::PriceAsc, ListingSort::PriceDesc, ListingSort::Rating];
        $price = [ListingSort::PriceAsc, ListingSort::PriceDesc];

        // The three curated pages lead with their own ranking instead of "Recommended".
        return match ($this) {
            self::BestSelling => [ListingSort::BestSelling, ListingSort::Newest, ListingSort::Rating, ...$price],
            self::NewIn => [ListingSort::Newest, ListingSort::BestSelling, ListingSort::Rating, ...$price],
            self::TopRated => [ListingSort::Rating, ListingSort::BestSelling, ListingSort::Newest, ...$price],
            self::Search => [ListingSort::Relevance, ...$common],
            self::Offers => [ListingSort::Discount, ...$common],
            default => [ListingSort::Recommended, ...$common],
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::BestSelling => 'storefront.best-sellers',
            self::NewIn => 'storefront.new-in',
            self::TopRated => 'storefront.top-rated',
            self::Offers => 'storefront.offers',
            self::Category => 'storefront.category',
            self::Search => 'storefront.search',
        };
    }

    /**
     * Static-text key suffix (catalog.listing.{key}.*).
     */
    public function textKey(): string
    {
        return $this->value;
    }
}
