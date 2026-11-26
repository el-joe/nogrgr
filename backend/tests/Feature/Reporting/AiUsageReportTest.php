<?php

use App\Modules\Ai\Models\AiUsageLog;

require_once __DIR__.'/ReportingHelpers.php';

/*
| ADM-070l Admin › Reports › AI usage: tokens per store × feature × provider × model from
| the AI usage log, store filter, platform rows.
*/

beforeEach(function (): void {
    $this->megz = reportTenant('Megz Store');
    $this->lina = reportTenant('Lina Boutique');
    $log = static fn (array $attributes) => tap(new AiUsageLog)->forceFill(array_merge(['feature' => 'store_translation', 'provider' => 'openai', 'model' => 'gpt-4.1-mini', 'prompt_tokens' => 100, 'completion_tokens' => 40], $attributes))->save();
    $log(['tenant_id' => $this->megz->id]);
    $log(['tenant_id' => $this->megz->id, 'prompt_tokens' => 50, 'completion_tokens' => 10]);
    $log(['tenant_id' => $this->lina->id, 'feature' => 'product_translation', 'provider' => 'freellmapi', 'model' => 'auto', 'prompt_tokens' => 7, 'completion_tokens' => 3]);
    $log(['tenant_id' => null, 'feature' => 'catalog_translation', 'provider' => 'freellmapi', 'model' => 'auto', 'prompt_tokens' => 20, 'completion_tokens' => 20]);
    $log(['tenant_id' => $this->megz->id, 'prompt_tokens' => 9999, 'created_at' => now()->subDays(90)]);
});

it('shows calls and tokens per store, feature and model', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');

    $this->get(route('admin.reports.show', 'ai_usage'))
        ->assertOk()
        ->assertSee(__('reporting::reports.tabs.ai_usage'))
        ->assertSee(__('reporting::reports.kpi.ai_tokens'))
        ->assertSee('250');

    $rows = $this->getJson(route('admin.reports.data', ['tab' => 'ai_usage', 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 3)
        ->json('data');

    expect(collect($rows)->pluck('total_tokens')->implode(' '))->toContain('200')->toContain('40')->toContain('10');
    expect(json_encode($rows, JSON_UNESCAPED_UNICODE))->toContain('Megz Store')->toContain(__('reporting::reports.platform'))->toContain('gpt-4.1-mini');
});

it('narrows to one store', function (): void {
    $this->actingAs(reportAdmin(['reports.view']), 'admin');

    $this->getJson(route('admin.reports.data', ['tab' => 'ai_usage', 'tenant_id' => $this->lina->id, 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1);
});
