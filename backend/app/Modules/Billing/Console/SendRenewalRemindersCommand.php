<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Services\Reminders\BillingReminderService;
use Illuminate\Console\Command;

/**
 * Subscription renewal reminders 7 / 3 / 1 days before the period ends (decision #124).
 * Scheduled daily; idempotent per subscription period and day (sent_reminders).
 *
 *   php artisan billing:renewal-reminders
 */
class SendRenewalRemindersCommand extends Command
{
    protected $signature = 'billing:renewal-reminders';

    protected $description = 'E-mail store owners whose plan ends in 7, 3 or 1 days';

    public function handle(BillingReminderService $reminders): int
    {
        $this->components->info($reminders->sendRenewalReminders().' renewal reminder(s) sent.');

        return self::SUCCESS;
    }
}
