<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;

/**
 * Central e-mail templates (decision #113). An old template moves only when its action
 * has a new event (table below) and every `{{variable}}` it uses has a new equivalent:
 * placeholders are rewritten to the new `{{ group.name }}` form, bodies pass the new
 * e-mail HTML sanitizer, and all old locales are kept. The row is matched by event, so
 * the seeded default is overwritten instead of duplicated. Anything else keeps the new
 * default and is reported.
 */
class EmailTemplatesStep implements LegacyStepHandler
{
    /** @var array<string, EmailTemplateEvent> old action => new event */
    private const EVENTS = [
        'tenant.order_confirmation' => EmailTemplateEvent::OrderConfirmation,
        'tenant.shipping_update' => EmailTemplateEvent::OrderShipped,
        'tenant.order_delivered' => EmailTemplateEvent::OrderDelivered,
        'tenant.order_cancelled' => EmailTemplateEvent::OrderCancelled,
        'tenant.refund_processed' => EmailTemplateEvent::OrderRefundProcessed,
        'tenant.subscription_activated' => EmailTemplateEvent::VendorSubscriptionActivated,
        'central.registration_complete_link' => EmailTemplateEvent::VendorRegistrationLink,
        'central.tenant_welcome' => EmailTemplateEvent::VendorWelcome,
    ];

    /** @var array<string, string> old variable => new variable */
    private const VARIABLES = [
        'app_name' => 'platform.name',
        'marketplace_name' => 'platform.name',
        'store_name' => 'store.name',
        'tenant_name' => 'vendor.name',
        'vendor_name' => 'vendor.name',
        'customer_name' => 'customer.name',
        'order_number' => 'order.number',
        'order_total' => 'order.total',
        'grand_total' => 'order.total',
        'order_url' => 'order.url',
        'invoice_number' => 'invoice.number',
        'package_name' => 'plan.name',
        'plan_name' => 'plan.name',
        'ends_at' => 'subscription.ends_at',
        'registration_url' => 'registration.url',
        'login_url' => 'login.url',
    ];

    public function step(): LegacyStep
    {
        return LegacyStep::EmailTemplates;
    }

    public function run(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();

        if (! $context->source->hasTable($source, 'email_templates')) {
            return;
        }

        $rows = $source->table('email_templates')->orderBy('id')->get();
        $translations = $context->source->hasTable($source, 'email_template_translations')
            ? $source->table('email_template_translations')->whereIn('email_template_id', $rows->pluck('id')->all())->get()->groupBy('email_template_id')
            : collect();

        $writer->transaction(function () use ($context, $writer, $rows, $translations): void {
            foreach ($rows as $row) {
                $context->report->read('central', 'email_templates');
                $id = (int) $row->id;
                $event = self::EVENTS[(string) $row->action] ?? null;

                if ($event === null) {
                    $context->report->issue(LegacyIssueLevel::Info, 'central', 'email_templates', (string) $row->action, 'Old e-mail template has no new event (new default kept)');
                    $context->report->skipped('central', 'email_templates');

                    continue;
                }

                $subject = ['en' => (string) $row->subject];
                $body = ['en' => (string) $row->body];

                foreach ($translations->get($id, collect()) as $translation) {
                    $locale = strtolower((string) $translation->locale);
                    $subject[$locale] = (string) $translation->subject;
                    $body[$locale] = (string) ($translation->body ?? '');
                }

                $unknown = [];
                $subject = array_map(function (string $text) use (&$unknown): string {
                    return $this->placeholders($text, $unknown);
                }, $subject);
                $body = array_filter(array_map(function (string $html) use (&$unknown): string {
                    return EmailHtmlSanitizer::clean($this->placeholders($html, $unknown));
                }, $body), static fn (string $html): bool => trim(strip_tags($html)) !== '');

                if ($unknown !== [] || $body === []) {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', 'email_templates', (string) $row->action, 'Old e-mail template uses variables without a new equivalent (new default kept)');
                    $context->report->skipped('central', 'email_templates');

                    continue;
                }

                $writer->upsert('email_template', $id, 'email_templates', [
                    'event' => $event->value,
                    'audience' => $event->audience()->value,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => ($row->status === 'active' ? EmailTemplateStatus::Active : EmailTemplateStatus::Inactive)->value,
                    'created_at' => $row->created_at,
                ], ['event' => $event->value]);
            }
        });
    }

    /**
     * Rewrites `{{ old_name }}` to `{{ new.name }}`; unknown names are collected.
     *
     * @param  list<string>  $unknown
     */
    private function placeholders(string $text, array &$unknown): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', static function (array $match) use (&$unknown): string {
            $name = strtolower($match[1]);
            $new = self::VARIABLES[$name] ?? (str_contains($name, '.') ? $name : null);

            if ($new === null) {
                $unknown[] = $name;

                return $match[0];
            }

            return '{{ '.$new.' }}';
        }, $text);
    }
}
