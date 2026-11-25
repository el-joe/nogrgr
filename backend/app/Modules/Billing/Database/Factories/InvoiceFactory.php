<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * tenant_id must be given. Prefer InvoiceService::issue() when totals matter.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-TEST-'.Str::upper(Str::random(10)),
            'type' => InvoiceType::Subscription,
            'status' => InvoiceStatus::Unpaid,
            'currency' => 'USD',
            'subtotal_minor' => 5900,
            'tax_rate_bp' => 0,
            'tax_minor' => 0,
            'total_minor' => 5900,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ];
    }
}
