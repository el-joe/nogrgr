<?php

use App\Modules\Identity\Models\Affiliate;

it('filters central rows by the current tenant only inside tenant context', function (): void {
    $acme = $this->createTenant('acme');
    $beta = $this->createTenant('beta');

    Affiliate::factory()->create(['tenant_id' => $beta->id, 'email' => 'beta@example.test']);
    Affiliate::factory()->create(['email' => 'independent@example.test']);

    $this->initializeTenancy($acme);

    $created = Affiliate::factory()->create(['email' => 'acme@example.test']);

    expect($created->tenant_id)->toBe($acme->id)
        ->and(Affiliate::query()->pluck('email')->all())->toBe(['acme@example.test'])
        ->and(Affiliate::forTenant($beta->id)->pluck('email')->all())->toBe(['beta@example.test'])
        ->and(Affiliate::withoutTenantScope()->count())->toBe(3);

    $this->endTenancy();

    expect(Affiliate::query()->count())->toBe(3);
});
