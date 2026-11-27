<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyTranslations;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Shipping\Enums\ShippingRuleStatus;

/**
 * Central shipping (decision #113).
 *
 * - `fixed_shipping_costs` (USD per gram per country) → `shipping_fixed_costs`, matched
 *   by country (the rate column has the same unit and precision).
 * - Each old `shipping_zones` row (one country, weight-band rates) becomes one new
 *   shipping rule for that country with one all-cities zone whose bands are the old
 *   rates (weights in grams, prices → USD cents). Old zones without a country become
 *   draft rules (no country = never quoted) so an admin can assign countries.
 *
 * The old system had no shipping-company table: companies are created in the new admin.
 */
class ShippingStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Shipping;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->source->central();

        if ($context->source->hasTable($source, 'fixed_shipping_costs')) {
            $this->fixedCosts($context);
        }

        if ($context->source->hasTable($source, 'shipping_zones')) {
            $this->zones($context);
        }

        $context->report->note('Shipping companies: the old system had none; create them in Admin › Shipping.');
    }

    private function fixedCosts(LegacyContext $context): void
    {
        $writer = $context->central;

        $writer->transaction(function () use ($context, $writer): void {
            foreach ($context->source->central()->table('fixed_shipping_costs')->orderBy('id')->get() as $row) {
                $context->report->read('central', 'fixed_shipping_costs');
                $countryId = $writer->id('country', (int) $row->country_id);

                if ($countryId === null) {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', 'fixed_shipping_costs', (int) $row->id, 'Fixed shipping cost of an unknown country (skipped)');
                    $context->report->skipped('central', 'fixed_shipping_costs');

                    continue;
                }

                if (strtoupper((string) $row->currency_code) !== 'USD') {
                    $context->report->issue(LegacyIssueLevel::Warning, 'central', 'fixed_shipping_costs', (int) $row->id, 'Non-USD fixed shipping rate kept as USD (check the rate)');
                }

                if (! (bool) $row->is_active) {
                    $context->report->issue(LegacyIssueLevel::Info, 'central', 'fixed_shipping_costs', (int) $row->id, 'Inactive old fixed shipping rate migrated (the new rate has no on/off switch)');
                }

                $writer->upsert('shipping_fixed_cost', (int) $row->id, 'shipping_fixed_costs', [
                    'country_id' => $countryId,
                    'price_per_gram' => number_format((float) $row->price_per_gram, 6, '.', ''),
                    'created_at' => $row->created_at,
                ], ['country_id' => $countryId]);
            }
        });
    }

    private function zones(LegacyContext $context): void
    {
        $writer = $context->central;
        $source = $context->source->central();
        $zones = $source->table('shipping_zones')->orderBy('id')->get();
        $rates = $context->source->hasTable($source, 'shipping_zone_rates') ? $source->table('shipping_zone_rates')->orderBy('id')->get()->groupBy('shipping_zone_id') : collect();
        $texts = $context->translations()->load('App\\Models\\ShippingZone', $zones->pluck('id')->map('intval')->all());

        $writer->transaction(function () use ($context, $writer, $zones, $rates, $texts): void {
            foreach ($zones as $row) {
                $context->report->read('central', 'shipping_zones');
                $id = (int) $row->id;
                $countryId = $row->country_id !== null ? $writer->id('country', (int) $row->country_id) : null;
                $name = mb_substr((string) (LegacyTranslations::field($texts, $id, 'name', (string) $row->name)['en'] ?? $row->name), 0, 120);
                $active = $row->status === 'active' && $countryId !== null;

                if ($countryId === null) {
                    $context->report->issue(LegacyIssueLevel::Info, 'central', 'shipping_zones', $id, 'Zone without a country migrated as a draft rule (assign countries in the admin)');
                }

                if ($row->branch_id !== null) {
                    $context->report->issue(LegacyIssueLevel::Info, 'central', 'shipping_zones', $id, 'Old branch link of the zone not migrated');
                }

                $ruleId = $writer->upsert('shipping_rule', $id, 'shipping_rules', [
                    'name' => $name,
                    'status' => ($active ? ShippingRuleStatus::Active : ShippingRuleStatus::Draft)->value,
                    'created_at' => $row->created_at,
                ]);
                $writer->syncPivot('shipping_rule_countries', 'shipping_rule_id', $ruleId, $countryId !== null && $countryId > 0 ? [['shipping_rule_id' => $ruleId, 'country_id' => $countryId]] : []);
                $zoneId = $writer->upsert('shipping_zone', $id, 'shipping_zones', [
                    'shipping_rule_id' => $ruleId,
                    'name' => $name,
                    'position' => 0,
                    'created_at' => $row->created_at,
                ]);

                $bands = [];

                foreach ($rates->get($id, collect())->values() as $position => $rate) {
                    $context->report->read('central', 'shipping_zone_rates');

                    if (! (bool) $rate->is_active) {
                        $context->report->issue(LegacyIssueLevel::Info, 'central', 'shipping_zone_rates', (int) $rate->id, 'Inactive old rate not migrated');
                        $context->report->skipped('central', 'shipping_zone_rates');

                        continue;
                    }

                    $bands[] = [
                        'shipping_zone_id' => $zoneId,
                        'name' => mb_substr((string) $rate->name, 0, 120),
                        'min_weight_grams' => max(0, (int) round((float) ($rate->min_weight ?? 0))),
                        'max_weight_grams' => $rate->max_weight !== null ? (int) round((float) $rate->max_weight) : null,
                        'price' => LegacyValues::minor($rate->price),
                        'position' => $position,
                        'created_at' => $rate->created_at,
                        'updated_at' => $rate->updated_at,
                    ];
                }

                $writer->syncPivot('shipping_zone_bands', 'shipping_zone_id', $zoneId, $bands);
            }
        });
    }
}
