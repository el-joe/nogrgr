<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\GeneralSettingsService;

require_once __DIR__.'/OwnerHelpers.php';

/*
| OWN-009 Help & support (QA-P2-007): contact channels from Admin › Settings › General,
| unconfigured channels hidden, empty state when nothing is set, sidebar link.
*/

beforeEach(function (): void {
    $this->owner = Owner::factory()->create();
});

it('redirects guests to the owner login', function (): void {
    $this->get(route('owner.support'))->assertRedirect();
});

it('shows the empty state when no support channel is configured', function (): void {
    $this->actingAs($this->owner, 'owner')
        ->get(route('owner.support'))
        ->assertOk()
        ->assertSee(__('platform::owner.support.empty_title'))
        ->assertDontSee('data-support-contacts', false)
        ->assertDontSee('data-support-socials', false);
});

it('lists only the configured channels from the general settings', function (): void {
    app(SettingsRepositoryContract::class)->put(GeneralSettingsService::GROUP, [
        'support_email' => 'help@nogrgr.test',
        'support_phone' => '+966 55 123 4567',
        'socials' => ['instagram' => 'https://instagram.com/nogrgr', 'x' => '', 'tiktok' => 'javascript:alert(1)'],
    ]);

    $this->actingAs($this->owner, 'owner')
        ->get(route('owner.support'))
        ->assertOk()
        ->assertDontSee(__('platform::owner.support.empty_title'))
        ->assertSee('href="mailto:help@nogrgr.test"', false)
        ->assertSee('href="tel:+966551234567"', false)
        ->assertSee('https://instagram.com/nogrgr', false)
        ->assertSee(__('integrations::general.socials.instagram'))
        ->assertDontSee(__('integrations::general.socials.x'))
        ->assertDontSee('javascript:alert', false);
});

it('hides the phone when only an e-mail is set', function (): void {
    app(SettingsRepositoryContract::class)->put(GeneralSettingsService::GROUP, ['support_email' => 'help@nogrgr.test']);

    $this->actingAs($this->owner, 'owner')
        ->get(route('owner.support'))
        ->assertOk()
        ->assertSee('data-support-channel="email"', false)
        ->assertDontSee('data-support-channel="phone"', false)
        ->assertDontSee('data-support-socials', false);
});

it('links the page from the owner sidebar in ar and en', function (): void {
    $this->actingAs($this->owner, 'owner')
        ->get(route('owner.support'))
        ->assertOk()
        ->assertSee(route('owner.support'), false)
        ->assertSee(__('navigation.owner.support'));

    $this->actingAs($this->owner, 'owner')
        ->get(route('owner.support', ['lang' => 'ar']))
        ->assertOk()
        ->assertSee('المساعدة والدعم');
});
