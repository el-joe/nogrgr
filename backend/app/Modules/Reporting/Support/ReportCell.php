<?php

namespace App\Modules\Reporting\Support;

use App\Modules\Reporting\DTOs\ReportColumn;
use App\Shared\Money\Money;

/**
 * Renders a report column value as an escaped table cell (HTML) or a CSV value.
 */
final class ReportCell
{
    public static function html(ReportColumn $column, object $row): string
    {
        $raw = $column->raw($row);
        $text = match ($column->type) {
            'money' => ReportFormat::money((int) $raw),
            'number' => ReportFormat::number((int) $raw),
            'decimal' => ReportFormat::number((float) $raw, 1),
            'percent' => ReportFormat::percent((float) $raw),
            'month' => is_string($raw) && $raw !== '' ? ReportFormat::month($raw) : '—',
            default => $raw === null || $raw === '' ? '—' : (string) $raw,
        };
        $secondary = $column->secondary !== null ? ($column->secondary)($row) : null;
        $numeric = $column->alignEnd();

        $html = '<span class="'.($numeric ? 'whitespace-nowrap font-medium tabular-nums text-ink' : 'block font-medium text-ink').'"'.($numeric ? ' dir="ltr"' : '').'>'.e($text).'</span>';

        if (is_string($secondary) && $secondary !== '') {
            $html = '<span class="grid min-w-0">'.$html.'<span class="truncate text-xs text-ink-secondary">'.e($secondary).'</span></span>';
        }

        return $html;
    }

    public static function csv(ReportColumn $column, object $row): string
    {
        $raw = $column->raw($row);

        return match ($column->type) {
            'money' => Money::ofMinor((int) $raw)->toDecimal(),
            'number' => (string) (int) $raw,
            'decimal', 'percent' => number_format((float) $raw, 1, '.', ''),
            default => (string) ($raw ?? ''),
        };
    }
}
