<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Localization\Enums\TranslationKeyStatus;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Support\TranslationFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;

/**
 * Admin › Localization › Translations (ADM-092 / 093) rows of one file in one language,
 * server-side over the merged files + overrides. Tabs: all, missing (selected
 * language), missing_ar, locked; search on key, English and the language value.
 */
final class TranslationsDataTable
{
    public function __construct(private readonly TranslationEditorService $editor) {}

    public function toJson(TranslationFile $file, string $locale, string $tab, ?string $search): JsonResponse
    {
        $rows = $this->filter($this->editor->rows($file, $locale), $file, $tab, $search);
        $canManage = (bool) auth('admin')->user()?->can('localization.translations.manage');
        $canLock = (bool) auth('admin')->user()?->can('localization.key-locks.manage');
        $context = ['file' => $file, 'locale' => $locale, 'canManage' => $canManage, 'canLock' => $canLock];

        return CollectionDataTable::create($rows->all())
            ->filter(static fn (): null => null)
            ->editColumn('key', fn (array $row): string => view('localization::admin.translations._key-cell', ['row' => $row] + $context)->render())
            ->editColumn('source', fn (array $row): string => view('localization::admin.translations._value-cell', ['value' => $row['source'], 'locale' => 'en'])->render())
            ->editColumn('value', fn (array $row): string => view('localization::admin.translations._value-cell', ['value' => $row['value'], 'locale' => $locale, 'status' => $row['status']])->render())
            ->addColumn('lock', fn (array $row): string => view('localization::admin.translations._lock-cell', ['row' => $row] + $context)->render())
            ->addColumn('actions', fn (array $row): string => view('localization::admin.translations._actions-cell', ['row' => $row] + $context)->render())
            ->rawColumns(['key', 'source', 'value', 'lock', 'actions'])
            ->toJson();
    }

    /**
     * @param  Collection<int, array{key: string, source: string|null, value: string|null, status: TranslationKeyStatus, locked: bool}>  $rows
     * @return Collection<int, array{key: string, source: string|null, value: string|null, status: TranslationKeyStatus, locked: bool}>
     */
    private function filter(Collection $rows, TranslationFile $file, string $tab, ?string $search): Collection
    {
        if ($tab === 'missing_ar') {
            $missingAr = $this->editor->rows($file, 'ar')->where('status', TranslationKeyStatus::Missing)->pluck('key')->flip();
            $rows = $rows->filter(fn (array $row): bool => $missingAr->has($row['key']));
        }

        $needle = $search !== null && trim($search) !== '' ? mb_strtolower(trim($search)) : null;

        if ($tab === 'missing') {
            $rows = $rows->filter(fn (array $row): bool => $row['status'] === TranslationKeyStatus::Missing);
        }

        if ($tab === 'locked') {
            $rows = $rows->filter(fn (array $row): bool => $row['locked']);
        }

        if ($needle !== null) {
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['key'].' '.$row['source'].' '.$row['value']), $needle));
        }

        return $rows->values();
    }
}
