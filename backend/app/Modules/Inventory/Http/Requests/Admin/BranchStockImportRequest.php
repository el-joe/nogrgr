<?php

namespace App\Modules\Inventory\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Branch stock CSV import: header row with `sku` and `on_hand` (or `quantity`) columns,
 * at most MAX_ROWS rows, quantities ≥ 0.
 */
class BranchStockImportRequest extends FormRequest
{
    public const MAX_ROWS = 5000;

    /**
     * @var list<array{sku: string, on_hand: int}>
     */
    private array $rows = [];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof UploadedFile && ($error = $this->parse($value)) !== null) {
                    $fail($error);
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => __('inventory::branches.stock.file')];
    }

    /**
     * @return list<array{sku: string, on_hand: int}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    private function parse(UploadedFile $file): ?string
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return __('inventory::branches.stock.unreadable');
        }

        $header = fgetcsv($handle, escape: '\\');
        $header = is_array($header) ? array_map(static fn (?string $cell): string => strtolower(trim((string) $cell, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header) : [];
        $skuIndex = array_search('sku', $header, true);
        $qtyIndex = array_search('on_hand', $header, true);
        $qtyIndex = $qtyIndex === false ? array_search('quantity', $header, true) : $qtyIndex;

        if ($skuIndex === false || $qtyIndex === false) {
            fclose($handle);

            return __('inventory::branches.stock.bad_header');
        }

        $line = 1;

        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            $sku = trim((string) ($cells[$skuIndex] ?? ''));
            $quantity = trim((string) ($cells[$qtyIndex] ?? ''));

            if ($sku === '' && $quantity === '') {
                continue;
            }

            if ($sku === '' || filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10_000_000]]) === false) {
                fclose($handle);

                return __('inventory::branches.stock.bad_row', ['line' => $line]);
            }

            if (count($this->rows) >= self::MAX_ROWS) {
                fclose($handle);

                return __('inventory::branches.stock.too_many', ['max' => self::MAX_ROWS]);
            }

            $this->rows[] = ['sku' => $sku, 'on_hand' => (int) $quantity];
        }

        fclose($handle);

        return $this->rows === [] ? __('inventory::branches.stock.empty_file') : null;
    }
}
