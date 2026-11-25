<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;

it('serves the central website home', function (): void {
    $this->get($this->centralUrl('/'))
        ->assertOk()
        ->assertSee(config('app.name'));
});

it('redirects guests from the admin panel to the admin login', function (): void {
    $this->get($this->centralUrl('/admin'))
        ->assertRedirect($this->centralUrl('/admin/login'));

    $this->get($this->centralUrl('/admin/login'))->assertOk();
});

it('redirects guests from the affiliate panel to the affiliate login', function (): void {
    $this->get($this->centralUrl('/affiliate'))
        ->assertRedirect($this->centralUrl('/affiliate/login'));

    $this->get($this->centralUrl('/affiliate/login'))->assertOk();
});

it('lets an admin with the permission into the dashboard and forbids one without', function (): void {
    $this->seed(CentralPermissionSeeder::class);

    $allowed = Admin::factory()->create();
    $allowed->assignRole('super-admin');

    $this->actingAs($allowed, 'admin')
        ->get($this->centralUrl('/admin'))
        ->assertOk();

    $denied = Admin::factory()->create();

    $this->actingAs($denied, 'admin')
        ->get($this->centralUrl('/admin'))
        ->assertForbidden();
});

it('lets an affiliate with the permission into the affiliate dashboard', function (): void {
    $this->seed(CentralPermissionSeeder::class);

    $affiliate = Affiliate::factory()->create();
    $affiliate->assignRole('affiliate');

    $this->actingAs($affiliate, 'affiliate')
        ->get($this->centralUrl('/affiliate'))
        ->assertOk();
});

it('redirects an authenticated admin away from the login page', function (): void {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get($this->centralUrl('/admin/login'))
        ->assertRedirect($this->centralUrl('/admin'));
});

it('redirects secondary central domains to the primary one', function (): void {
    config(['tenancy.central_domains' => ['localhost', '127.0.0.1']]);

    $this->get('http://127.0.0.1/admin/login')
        ->assertRedirect('http://localhost/admin/login');
});

it('answers the health check', function (): void {
    $this->get($this->centralUrl('/up'))->assertOk();
});
