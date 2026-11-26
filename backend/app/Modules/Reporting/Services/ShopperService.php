<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ShopperStatus;
use App\Modules\Reporting\Repositories\Contracts\ReportFactRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Vendors › Shoppers (ADM-043, Figma 73:52854): read-only buyers of every store
 * from order facts. Personal data is masked (first name + initial, masked e-mail) on the
 * page and in the export.
 */
class ShopperService extends BaseService
{
    public function __construct(private readonly ReportFactRepositoryContract $facts) {}

    /**
     * @return list<array{key: string, value: string, raw: int|float, format: string, icon: string}>
     */
    public function kpis(ReportFilterData $filters): array
    {
        $totals = $this->facts->shopperTotals($filters);

        return [
            ['key' => 'shoppers', 'value' => ReportFormat::number($totals['shoppers']), 'raw' => $totals['shoppers'], 'format' => 'number', 'icon' => 'users'],
            ['key' => 'new', 'value' => ReportFormat::number($totals['new']), 'raw' => $totals['new'], 'format' => 'number', 'icon' => 'plus'],
            ['key' => 'returning', 'value' => ReportFormat::percent(ReportMath::percent($totals['returning'], $totals['shoppers'])), 'raw' => ReportMath::percent($totals['returning'], $totals['shoppers']), 'format' => 'percent', 'icon' => 'refresh'],
            ['key' => 'avg_orders', 'value' => ReportFormat::number($totals['shoppers'] > 0 ? round($totals['orders'] / $totals['shoppers'], 1) : 0, 1), 'raw' => $totals['shoppers'] > 0 ? round($totals['orders'] / $totals['shoppers'], 1) : 0, 'format' => 'number', 'icon' => 'cart'],
        ];
    }

    /**
     * @param  array{search?: string|null, status?: string|null, new_only?: bool}  $options
     */
    public function rows(ReportFilterData $filters, array $options): Builder
    {
        return $this->facts->shoppers($filters, $options);
    }

    /**
     * @param  array{search?: string|null, status?: string|null, new_only?: bool}  $options
     * @param  array<int, string>  $countries
     */
    public function export(ReportFilterData $filters, array $options, array $countries): StreamedResponse
    {
        $rows = $this->rows($filters, $options)->orderByDesc('sh.last_order_at');

        return response()->streamDownload(function () use ($rows, $countries): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('reporting::shoppers.columns.shopper'), __('reporting::shoppers.columns.email'), __('reporting::shoppers.columns.store'),
                __('reporting::shoppers.columns.country'), __('reporting::shoppers.columns.orders'), __('reporting::shoppers.columns.spent'),
                __('reporting::shoppers.columns.status'), __('reporting::shoppers.columns.first_order'), __('reporting::shoppers.columns.last_order'),
            ], escape: '\\');

            foreach ($rows->cursor() as $row) {
                fputcsv($out, [
                    self::maskName($row->customer_name),
                    self::maskEmail($row->customer_email),
                    (string) $row->tenant_name,
                    $countries[(int) $row->country_id] ?? '',
                    (int) $row->orders,
                    Money::ofMinor((int) $row->spent)->toDecimal(),
                    ShopperStatus::from((string) $row->shopper_status)->label(),
                    substr((string) $row->first_order_at, 0, 10),
                    substr((string) $row->last_order_at, 0, 10),
                ], escape: '\\');
            }

            fclose($out);
        }, 'shoppers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * "Ahmed Ali Hassan" → "Ahmed A."
     */
    public static function maskName(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return __('reporting::shoppers.guest');
        }

        $first = array_shift($parts);

        return $parts === [] ? $first : $first.' '.Str::upper(mb_substr((string) end($parts), 0, 1)).'.';
    }

    /**
     * "ahmed@gmail.com" → "a•••@gmail.com"
     */
    public static function maskEmail(?string $email): string
    {
        $email = trim((string) $email);

        if (! str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'•••@'.$domain;
    }
}
