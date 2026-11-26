<?php

namespace App\Modules\Localization\DataTables;

use App\Modules\Localization\Enums\StoreContentType;
use App\Modules\Localization\Services\StoreTranslationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Settings › Store translations › Store content (VEN-075): one translatable
 * field of the store's own records (categories, own products, flash sales) with the
 * English text, the value in the chosen language and whether it was translated by hand.
 */
final class StoreContentTranslationsDataTable
{
    public function __construct(private readonly StoreTranslationService $translations) {}

    public function toJson(StoreContentType $type, string $field, string $locale, ?string $search, bool $canManage): JsonResponse
    {
        $locked = $this->translations->lockedContent($type, $locale);
        $title = $type->titleField();
        $text = static fn (Model $record, string $name, string $in): ?string => method_exists($record, 'getTranslation') ? ((string) $record->getTranslation($name, $in, false) ?: null) : null;

        return (new EloquentDataTable($this->translations->contentQuery($type, $search)))
            ->filter(static fn (): null => null)
            ->addColumn('record', fn (Model $record): string => view('localization::vendor.translations._record-cell', ['title' => $text($record, $title, app()->getLocale()) ?? $text($record, $title, 'en') ?? '#'.$record->getKey(), 'id' => $record->getKey()])->render())
            ->addColumn('english', fn (Model $record): string => view('localization::vendor.translations._text-cell', ['value' => $text($record, $field, 'en'), 'locale' => 'en'])->render())
            ->addColumn('value', fn (Model $record): string => view('localization::vendor.translations._text-cell', ['value' => $text($record, $field, $locale), 'locale' => $locale])->render())
            ->addColumn('lock', fn (Model $record): string => view('localization::vendor.translations._lock-cell', ['platform' => false, 'edited' => isset($locked[$record->getKey().'|'.$field])])->render())
            ->addColumn('actions', fn (Model $record): string => view('localization::vendor.translations._content-actions', [
                'canManage' => $canManage,
                'type' => $type,
                'id' => (int) $record->getKey(),
                'field' => $field,
                'locale' => $locale,
                'value' => $text($record, $field, $locale),
                'english' => $text($record, $field, 'en'),
                'locked' => isset($locked[$record->getKey().'|'.$field]),
            ])->render())
            ->orderColumn('record', static fn ($query, string $order) => $query->orderBy('id', $order === 'desc' ? 'desc' : 'asc'))
            ->rawColumns(['record', 'english', 'value', 'lock', 'actions'])
            ->toJson();
    }
}
