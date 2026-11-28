<?php

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/FulfilmentHelpers.php';

/*
| Decision #138: order numbers come from a locked per-store counter, not from max(id).
*/

beforeEach(function (): void {
    Notification::fake();
    $this->shop = shopSetup();
    $this->next = fn (): string => app(OrderRepositoryContract::class)->nextNumber();
});

it('numbers orders without gaps, whatever the ids are', function (): void {
    $this->shop->tenant->run(function (): void {
        expect(($this->next)())->toBe('ORD-10001')->and(($this->next)())->toBe('ORD-10002');
    });
});

it('gives the number back when the order is rolled back', function (): void {
    $this->shop->tenant->run(function (): void {
        rescue(fn () => DB::transaction(function (): void {
            ($this->next)();

            throw new RuntimeException('order failed');
        }), report: false);

        expect(($this->next)())->toBe('ORD-10001');
    });
});

it('locks the counter row so concurrent checkouts queue up', function (): void {
    $this->shop->tenant->run(function (): void {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });
        ($this->next)();

        $locked = array_filter($statements, static fn (string $sql): bool => str_contains($sql, 'order_sequences') && str_contains($sql, 'for update'));

        expect($locked)->not->toBeEmpty();
    });
})->skip(fn (): bool => config('database.connections.'.config('database.default').'.driver') === 'sqlite', 'SQLite has no row locks.');

it('skips a number already used by an imported order', function (): void {
    shopGateway($this->shop->tenant);
    $order = paidShopOrder($this->shop, shopCustomer($this->shop), [$this->shop->catalog->id => 1]);

    expect($order->number)->toBe('ORD-10001');

    $this->shop->tenant->run(function () use ($order): void {
        Order::query()->whereKey($order->id)->update(['number' => 'ORD-10002']);

        expect(($this->next)())->toBe('ORD-10003');
    });
});
