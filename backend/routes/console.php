<?php

use App\Modules\Billing\Jobs\ExpireSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Billing: lapsed subscriptions → past_due / expired.
Schedule::job(new ExpireSubscriptions)->hourly()->name('billing:expire-subscriptions')->withoutOverlapping();
