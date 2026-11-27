<?php

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use App\Modules\Notifications\Notifications\PanelNotification;
use Illuminate\Support\Str;

/*
| QA-FINAL-008: in-app notifications store our own panel links as relative paths (mails
| keep the absolute url()), and rows stored with an old absolute host still open on the
| reader's host.
*/

it('stores the inbox link as a path while the mail keeps the absolute url', function (): void {
    $notification = new class extends PanelNotification
    {
        public function category(): NotificationCategory
        {
            return NotificationCategory::cases()[0];
        }

        public function event(): string
        {
            return 'link_test';
        }

        protected function titleKey(): string
        {
            return 'ui.save';
        }

        protected function bodyKey(): string
        {
            return 'ui.save';
        }

        protected function url(): ?string
        {
            return url('/admin/orders/7?tab=items');
        }

        public function absolute(): ?string
        {
            return $this->url();
        }
    };

    expect($notification->toArray(new stdClass)['url'])->toBe('/admin/orders/7?tab=items')
        ->and($notification->absolute())->toStartWith('http');
});

it('resolves old absolute panel links of stored rows on the current host', function (): void {
    $record = new PanelNotificationRecord;
    $record->forceFill(['id' => (string) Str::uuid(), 'data' => ['url' => 'http://127.0.0.1:8099/admin/vendors/3']]);

    expect($record->targetUrl())->toBe('/admin/vendors/3');

    $record->forceFill(['data' => ['url' => 'https://shop.example.com/products/shoe']]);

    expect($record->targetUrl())->toBe('https://shop.example.com/products/shoe');
});
