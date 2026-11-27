<?php

use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Catalog\Notifications\StoreRequestNotification;
use App\Modules\Inventory\Notifications\BackInStockNotification;
use App\Modules\Support\Notifications\StoreSupportNotification;
use App\Modules\Support\Notifications\SupportTicketNotification;

/*
| RQA-ADM-006: the queue worker unserialises the notification; subclasses of the support /
| request notifications failed with "Cannot initialize readonly property".
*/

it('restores a queued store support notification with all its properties', function (): void {
    $notification = new StoreSupportNotification('admin_replied', ['ticket' => 'TCK-1'], 'vendor.support.show', ['ticket' => 7], 12, 'https://shop.test/vendor/support/7');

    $restored = unserialize(serialize($notification));

    expect($restored)->toBeInstanceOf(StoreSupportNotification::class)
        ->and($restored->eventName)->toBe('admin_replied')
        ->and($restored->parameters)->toBe(['ticket' => 'TCK-1'])
        ->and($restored->withMail)->toBeTrue()
        ->and($restored->vendorRoute)->toBe('vendor.support.show')
        ->and($restored->routeParameters)->toBe(['ticket' => 7])
        ->and($restored->mailStaffId)->toBe(12)
        ->and($restored->ticketUrl)->toBe('https://shop.test/vendor/support/7');
});

it('restores the other subclassed queued notifications', function (): void {
    $store = unserialize(serialize(new StoreRequestNotification('approved', ['name' => 'Mug'], 'vendor.requests.index', ['tab' => 'products'])));
    $ticket = unserialize(serialize(new SupportTicketNotification('requester_replied', ['ticket' => 'TCK-2'], 'https://x.test/t/2', false)));
    $request = unserialize(serialize(new RequestUpdateNotification('rejected', ['name' => 'Cup'], 'https://x.test/r/1')));
    $backInStock = unserialize(serialize(new BackInStockNotification(5, ['product.name' => 'Lamp'], 'ar')));

    expect($store->eventName)->toBe('approved')->and($store->parameters)->toBe(['name' => 'Mug'])
        ->and($store->vendorRoute)->toBe('vendor.requests.index')->and($store->routeParameters)->toBe(['tab' => 'products'])
        ->and($ticket->link)->toBe('https://x.test/t/2')->and($ticket->withMail)->toBeFalse()
        ->and($request->link)->toBe('https://x.test/r/1')
        ->and($backInStock->storeProductId)->toBe(5)->and($backInStock->mailLocale)->toBe('ar');
});
