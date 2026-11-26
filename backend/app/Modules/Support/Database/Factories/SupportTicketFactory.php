<?php

namespace App\Modules\Support\Database\Factories;

use App\Modules\Support\Enums\TicketAuthor;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketPriority;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'opened_by_type' => TicketAuthor::Vendor,
            'opened_by_id' => 1,
            'opened_by_name' => fake()->name(),
            'opened_by_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(5),
            'category' => fake()->randomElement(TicketCategory::cases()),
            'priority' => TicketPriority::Normal,
            'status' => TicketStatus::Open,
            'last_reply_by' => TicketAuthor::Vendor,
            'last_reply_at' => now(),
            'admin_unread' => true,
            'requester_unread' => false,
            'sla_due_at' => now()->addDay(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(static function (SupportTicket $ticket): void {
            if ($ticket->reference === null) {
                $ticket->forceFill(['reference' => 'T-'.(1000 + $ticket->id)])->saveQuietly();
            }
        });
    }
}
