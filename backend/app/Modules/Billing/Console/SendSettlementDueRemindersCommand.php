<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Services\Reminders\BillingReminderService;
use Illuminate\Console\Command;

/**
 * Weekly "settlement due" reminder to stores that owe the platform (ledger receivable >
 * payable, decision #124). Idempotent per store and ISO week (sent_reminders).
 *
 *   php artisan billing:settlement-reminders
 */
class SendSettlementDueRemindersCommand extends Command
{
    protected $signature = 'billing:settlement-reminders';

    protected $description = 'Remind stores that owe the platform a settlement (once per week)';

    public function handle(BillingReminderService $reminders): int
    {
        $this->components->info($reminders->sendSettlementDueReminders().' settlement reminder(s) sent.');

        return self::SUCCESS;
    }
}
