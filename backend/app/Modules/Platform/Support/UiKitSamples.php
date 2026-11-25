<?php

namespace App\Modules\Platform\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Sample records for the component library (/admin/ui-kit) only: they let the data
 * table, pagination, charts and notification list show every state. Nothing here is
 * used by real pages.
 */
final class UiKitSamples
{
    /**
     * @return Collection<int, array{id: int, name: string, sku: string, category: string, price: string, stock: int, status: string, updated: string}>
     */
    public static function products(): Collection
    {
        $names = ['Wireless Earbuds Pro', 'Linen Summer Dress', 'Ceramic Pour-over Set', 'Vitamin C Serum 30ml', 'Smart Watch Series 4', 'Leather Card Holder', 'Oud Perfume 100ml', 'Running Shoes Air', 'Bamboo Cutting Board', 'Kids Story Book Set', 'Desk Lamp LED', 'Travel Backpack 28L'];
        $categories = ['Electronics', 'Fashion', 'Home & Kitchen', 'Beauty', 'Electronics', 'Fashion', 'Beauty', 'Sports', 'Home & Kitchen', 'Books', 'Home & Kitchen', 'Travel'];
        $statuses = ['active', 'active', 'out_of_stock', 'pending_review', 'draft'];

        return collect(range(1, 36))->map(function (int $id) use ($names, $categories, $statuses): array {
            $index = ($id - 1) % count($names);

            return [
                'id' => $id,
                'name' => $names[$index],
                'sku' => sprintf('NG-%05d', 10200 + $id * 7),
                'category' => $categories[$index],
                'price' => number_format(49 + ($id * 37) % 900, 2),
                'stock' => ($id * 53) % 1300,
                'status' => $statuses[$id % count($statuses)],
                'updated' => now()->subHours($id * 5)->toDateTimeString(),
            ];
        });
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, sku: string, category: string, price: string, stock: int, status: string, updated: string}>
     */
    public static function paginator(int $page = 1): LengthAwarePaginator
    {
        $items = self::products();

        return new LengthAwarePaginator($items->forPage($page, 5)->values(), $items->count(), 5, $page, ['path' => request()->url(), 'pageName' => 'page']);
    }

    /**
     * @return list<array{title: string, meta: string, time: string, icon: string, tone: string, unread: bool, url: string}>
     */
    public static function notifications(): array
    {
        $url = request()->url();

        return [
            ['title' => 'New order #ORD-10431', 'meta' => 'Megz Store · USD 112.00', 'time' => '2m', 'icon' => 'cart', 'tone' => 'brand', 'unread' => true, 'url' => $url],
            ['title' => 'Return request RMA-882', 'meta' => 'Customer reported a damaged item', 'time' => '18m', 'icon' => 'return', 'tone' => 'warning', 'unread' => true, 'url' => $url],
            ['title' => 'Vendor registration pending', 'meta' => 'Lina Boutique completed KYC', 'time' => '1h', 'icon' => 'store', 'tone' => 'info', 'unread' => true, 'url' => $url],
            ['title' => 'Payout failed', 'meta' => 'Insufficient balance on the gateway', 'time' => '3h', 'icon' => 'credit-card', 'tone' => 'danger', 'unread' => false, 'url' => $url],
            ['title' => 'Currency rates synced', 'meta' => '168 currencies updated', 'time' => '6h', 'icon' => 'check-circle', 'tone' => 'success', 'unread' => false, 'url' => $url],
        ];
    }

    /**
     * @return array<string, array{labels: list<string>, series: list<array<string, mixed>>}>
     */
    public static function lineRanges(): array
    {
        $build = static function (int $points, string $unit): array {
            $labels = [];
            $gross = [];
            $platform = [];

            for ($index = 0; $index < $points; $index++) {
                $labels[] = $unit === 'month'
                    ? now()->subMonths($points - 1 - $index)->locale(app()->getLocale())->isoFormat('MMM')
                    : now()->subDays($points - 1 - $index)->locale(app()->getLocale())->isoFormat('D MMM');
                $gross[] = 9000 + $index * 900 + ($index % 3) * 1800;
                $platform[] = 2500 + $index * 260 + ($index % 2) * 400;
            }

            return ['labels' => $labels, 'series' => [
                ['label' => __('platform::ui-kit.sample.gross'), 'data' => $gross, 'tone' => 'brand'],
                ['label' => __('platform::ui-kit.sample.platform'), 'data' => $platform, 'tone' => 'ink'],
            ]];
        };

        return ['7d' => $build(7, 'day'), '30d' => $build(30, 'day'), '12m' => $build(12, 'month')];
    }
}
