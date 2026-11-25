<?php

namespace App\Modules\Notifications\Database\Factories;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => EmailTemplateEvent::OrderShipped,
            'audience' => EmailTemplateEvent::OrderShipped->audience(),
            'subject' => ['en' => 'Your order {{ order.number }} is on its way', 'ar' => 'طلبك {{ order.number }} في الطريق'],
            'body' => ['en' => '<h2>Good news, {{ customer.first_name }}!</h2><p>Track it with {{ shipment.tracking_no }}.</p>', 'ar' => '<h2>أخبار سارة يا {{ customer.first_name }}!</h2><p>تتبّعه برقم {{ shipment.tracking_no }}.</p>'],
            'status' => EmailTemplateStatus::Active,
        ];
    }

    public function event(EmailTemplateEvent $event): static
    {
        return $this->state(['event' => $event, 'audience' => $event->audience()]);
    }
}
