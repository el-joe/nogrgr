<?php

namespace App\Modules\Notifications\DataTables;

use App\Modules\Notifications\DTOs\StoreEmailTemplateRow;
use App\Modules\Notifications\Services\StoreEmailTemplateService;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\CollectionDataTable;

/**
 * Vendor › Settings › Email templates (VEN-080, Figma Vendor Panel 91:114835): one row
 * per shopper e-mail — name + event, source (Custom / Platform default / Built-in),
 * languages, updated, actions (edit, preview, reset). The list merges the central
 * platform templates with the store's copies, so it is served from memory (small, fixed).
 * Filters: search, source.
 */
final class VendorEmailTemplatesDataTable
{
    public const FILTERS = ['search', 'source'];

    public function __construct(private readonly StoreEmailTemplateService $templates) {}

    public function toJsonFromRequest(Request $request, bool $canManage): JsonResponse
    {
        $filters = DataTableFilters::fromRequest($request, self::FILTERS);
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $source = (string) ($filters['source'] ?? '');

        $rows = $this->templates->rows()
            ->filter(static fn (StoreEmailTemplateRow $row): bool => ($search === '' || str_contains(mb_strtolower($row->event->label().' '.$row->event->value), $search))
                && ($source === '' || $row->source() === $source))
            ->values()
            ->map(static fn (StoreEmailTemplateRow $row): array => [
                'id' => $row->event->value,
                'template' => view('notifications::vendor.email-templates._name-cell', ['row' => $row])->render(),
                'source' => view('notifications::vendor.email-templates._source-cell', ['row' => $row])->render(),
                'languages' => e(implode(' · ', array_map('strtoupper', $row->completeLocales())) ?: '—'),
                'updated' => e($row->updatedAt()?->translatedFormat('d M Y') ?? '—'),
                'actions' => view('notifications::vendor.email-templates._actions-cell', ['row' => $row, 'canManage' => $canManage])->render(),
            ]);

        return CollectionDataTable::create($rows->all())
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->rawColumns(['template', 'source', 'actions'])
            ->toJson();
    }
}
