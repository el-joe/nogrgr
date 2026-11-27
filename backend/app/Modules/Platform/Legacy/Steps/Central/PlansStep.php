<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyTranslations;
use App\Modules\Platform\Legacy\Support\LegacyValues;

/**
 * Old `packages` → `plans`. The old single price + term becomes the monthly or yearly
 * price (the other one stays 0 until an admin sets it); negative limits = unlimited.
 */
class PlansStep implements LegacyStepHandler
{
    private const LIMITS = [
        'categories_count' => PlanLimit::Categories,
        'products_limit' => PlanLimit::Products,
        'banners_limit' => PlanLimit::Banners,
        'languages_limit' => PlanLimit::Languages,
        'orders_per_month_limit' => PlanLimit::OrdersPerMonth,
        'ai_calls_limit' => PlanLimit::AiCalls,
        'image_searches_limit' => PlanLimit::ImageSearches,
    ];

    public function step(): LegacyStep
    {
        return LegacyStep::Plans;
    }

    public function run(LegacyContext $context): void
    {
        $rows = $context->source->central()->table('packages')->orderBy('id')->get();
        $texts = $context->translations()->load('App\\Models\\Package', $rows->pluck('id')->map('intval')->all());

        foreach ($rows as $row) {
            $context->report->read('central', 'plans');
            $price = LegacyValues::minor($row->price);
            $yearly = $row->term === 'yearly';
            $limits = [];

            foreach (self::LIMITS as $column => $limit) {
                $value = isset($row->{$column}) ? (int) $row->{$column} : -1;
                $limits[$limit->value] = $value < 0 ? null : $value;
            }

            $features = LegacyValues::json($row->features) ?? [];

            if ((bool) ($row->ai_translation_enabled ?? false)) {
                $features[] = 'ai_translation';
            }

            $context->central->upsert('plan', (int) $row->id, 'plans', [
                'name' => LegacyTranslations::field($texts, (int) $row->id, 'name', (string) $row->name),
                'description' => LegacyTranslations::field($texts, (int) $row->id, 'description'),
                'icon' => $row->icon !== null ? mb_substr((string) $row->icon, 0, 64) : null,
                'status' => ($row->status === 'published' ? PlanStatus::Active : PlanStatus::Inactive)->value,
                'monthly_price_minor' => $yearly ? 0 : $price,
                'yearly_price_minor' => $yearly ? $price : 0,
                'currency' => 'USD',
                'trial_days' => (int) $row->trial_days,
                'sort_order' => (int) $row->id,
                'limits' => $limits,
                'features' => array_values(array_unique(array_filter($features, 'is_string'))),
                'created_at' => $row->created_at,
            ]);
        }
    }
}
