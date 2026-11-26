<?php

use App\Modules\Platform\Enums\DeveloperDocTopic;

require_once __DIR__.'/../Reporting/ReportingHelpers.php';

/*
| Admin › System tools › Developer docs (ADM-115 / 115a).
*/

it('redirects guests and forbids admins without the docs permission', function (): void {
    $this->get(route('admin.system.docs.index'))->assertRedirect(route('admin.login'));

    $this->actingAs(reportAdmin(['reports.view']), 'admin');
    $this->get(route('admin.system.docs.index'))->assertForbidden();
});

it('opens the getting started topic on the index with every topic listed', function (): void {
    $this->actingAs(reportAdmin(['system.docs.view']), 'admin');

    $response = $this->get(route('admin.system.docs.index'))->assertOk()
        ->assertSee(__('platform::developer_docs.topics.getting-started.summary'));

    foreach (DeveloperDocTopic::cases() as $topic) {
        $response->assertSee(route('admin.system.docs.show', $topic->value));
    }
});

it('shows every topic with real platform routes', function (DeveloperDocTopic $topic, string $snippet): void {
    $this->actingAs(reportAdmin(['system.docs.view']), 'admin');

    $this->get(route('admin.system.docs.show', $topic->value))
        ->assertOk()
        ->assertSee($topic->label())
        ->assertSee($snippet, false);
})->with([
    [DeveloperDocTopic::GettingStarted, '/store-api/v1'],
    [DeveloperDocTopic::StoreApi, 'X-Cart-Token'],
    [DeveloperDocTopic::Webhooks, '/webhooks/payments/{gateway}'],
    [DeveloperDocTopic::MobileApi, '/api/mobile/v1/ping'],
    [DeveloperDocTopic::Themes, 'store_theme'],
]);

it('returns 404 for an unknown topic', function (): void {
    $this->actingAs(reportAdmin(['system.docs.view']), 'admin');

    $this->get(route('admin.system.docs.index').'/twig')->assertNotFound();
});

it('documents only routes that exist', function (): void {
    foreach (['store-api.v1.cart.show', 'store-api.v1.checkout.place', 'store-api.v1.payments.webhook', 'central.webhooks.payments', 'api.mobile.v1.ping', 'vendor.store.themes.index'] as $name) {
        expect(Route::has($name))->toBeTrue($name);
    }
});
