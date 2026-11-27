<?php

namespace App\Modules\Platform\Legacy\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;

/**
 * Central look-ups of migrated orders for central after-sales / finance rows that only
 * carry the old order uuid: the central `order_facts` row (one query per chunk, never
 * per row). Old central rows name orders by their uuid (`order_number` = uuid).
 */
final class LegacyOrders
{
    /**
     * @param  list<array{0: int, 1: string}>  $pairs  [new tenant id, order uuid]
     * @return array<string, object{order_id: int|null, order_number: string, order_uuid: string, grand_total: int, customer_id: int|null, customer_name: string|null}> keyed "<tenant>|<uuid>"
     */
    public static function facts(Connection $central, array $pairs): array
    {
        $pairs = array_values(array_filter($pairs, static fn (array $pair): bool => $pair[0] > 0 && $pair[1] !== ''));

        if ($pairs === []) {
            return [];
        }

        $out = [];

        foreach (collect($pairs)->groupBy(0) as $tenantId => $rows) {
            $rows = $central->table('order_facts')
                ->where('tenant_id', (int) $tenantId)
                ->whereIn('order_uuid', $rows->pluck(1)->unique()->values()->all())
                ->get(['order_id', 'order_number', 'order_uuid', 'grand_total', 'customer_id', 'customer_name']);

            foreach ($rows as $row) {
                $out[$tenantId.'|'.$row->order_uuid] = $row;
            }
        }

        return $out;
    }

    /**
     * The old order reference as a uuid: kept when it is one, else a stable uuid derived
     * from it (so a re-run produces the same value).
     */
    public static function uuid(string $reference): string
    {
        if (Str::isUuid($reference)) {
            return strtolower($reference);
        }

        $hash = md5('legacy-order:'.$reference);

        return sprintf('%s-%s-4%s-8%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3), substr($hash, 17, 3), substr($hash, 20, 12));
    }

    /**
     * The order number shown for a row whose order was not migrated: L-<first 8 of uuid>.
     */
    public static function fallbackNumber(string $uuid): string
    {
        return 'L-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 8));
    }
}
