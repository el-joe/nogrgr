<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Localization\Services\StoreTranslationService;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\CollectionDataTable;

/**
 * Vendor › Settings › Store translations (VEN-075): storefront static texts of one
 * language, server-side over the base key set ⊕ the store's rows. Tabs: all, missing,
 * locked (by the platform), edited; search on key, English and the language value.
 */
final class StoreStaticTextsDataTable
{
    public function __construct(private readonly StoreTranslationService $translations) {}

    public function toJson(string $locale, string $tab, ?string $search, bool $canManage): JsonResponse
    {
        $rows = $this->translations->staticRows($locale, $tab, $search);
        $context = ['locale' => $locale, 'canManage' => $canManage];

        return CollectionDataTable::create($rows->all())
            ->filter(static fn (): null => null)
            ->editColumn('key', fn (array $row): string => view('localization::vendor.translations._key-cell', ['row' => $row])->render())
            ->editColumn('english', fn (array $row): string => view('localization::vendor.translations._text-cell', ['value' => $row['english'], 'locale' => 'en'])->render())
            ->editColumn('value', fn (array $row): string => view('localization::vendor.translations._text-cell', ['value' => $row['value'], 'locale' => $locale])->render())
            ->addColumn('lock', fn (array $row): string => view('localization::vendor.translations._lock-cell', ['platform' => $row['platform_locked'], 'edited' => $row['is_locked'] || (bool) $row['source']?->isManual()])->render())
            ->addColumn('actions', fn (array $row): string => view('localization::vendor.translations._static-actions', ['row' => $row] + $context)->render())
            ->rawColumns(['key', 'english', 'value', 'lock', 'actions'])
            ->toJson();
    }
}
