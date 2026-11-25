<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

function uiKitAdmin(bool $withPermission = true): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();

    if ($withPermission) {
        $admin->assignRole('super-admin');
    } else {
        $admin->givePermissionTo('dashboard.view');
    }

    return $admin;
}

it('shows the component library to admins with system.ui-kit.view', function (): void {
    $this->actingAs(uiKitAdmin(), 'admin')
        ->get($this->centralUrl('/admin/ui-kit'))
        ->assertOk()
        ->assertSee(__('platform::ui-kit.title'))
        ->assertSee('id="ui-confirm"', false)
        ->assertSee('data-datatable', false)
        ->assertSee('data-sidebar', false)
        ->assertSee(__('navigation.admin.ui_kit'));
});

it('forbids the component library without the permission', function (): void {
    $this->actingAs(uiKitAdmin(false), 'admin')
        ->get($this->centralUrl('/admin/ui-kit'))
        ->assertForbidden();
});

it('redirects guests away from the component library', function (): void {
    $this->get($this->centralUrl('/admin/ui-kit'))
        ->assertRedirect($this->centralUrl('/admin/login'));
});

it('serves the sample data table as DataTables JSON with filters', function (): void {
    $this->actingAs(uiKitAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/ui-kit/table?draw=1&start=0&length=10&status=draft'))
        ->assertOk()
        ->assertJsonPath('draw', 1)
        ->assertJsonPath('data.0.status', fn (string $html) => str_contains($html, __('ui.status.draft')));
});

it('answers the validation demo with the 422 contract', function (): void {
    $this->actingAs(uiKitAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/form'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'title.en', 'title.ar', 'terms']);
});

it('answers bulk actions with a JSON success message', function (): void {
    $this->actingAs(uiKitAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/bulk'), ['ids' => [1, 2]])
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('renders the admin shell, dashboard and only registered sidebar links', function (): void {
    $this->actingAs(uiKitAdmin(), 'admin')
        ->get($this->centralUrl('/admin'))
        ->assertOk()
        ->assertSee(__('platform::dashboard.kpi.gross_sales'))
        ->assertSee(__('platform::dashboard.attention.title'))
        ->assertSee(__('navigation.admin.dashboard'))
        ->assertDontSee(__('navigation.admin.shipping_queue'))
        ->assertDontSee(__('navigation.admin.audit_log'));
});

it('renders the dashboard in Arabic as RTL', function (): void {
    $this->actingAs(uiKitAdmin(), 'admin')
        ->get($this->centralUrl('/admin?lang=ar'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('platform::dashboard.kpi.gross_sales', [], 'ar'))
        ->assertCookie('locale', 'ar', false);
});

it('restyles the admin sign-in page with live platform stats', function (): void {
    $this->get($this->centralUrl('/admin/login'))
        ->assertOk()
        ->assertSee(__('identity::auth.panel.admin.headline'))
        ->assertSee(__('identity::auth.stats.active_stores'))
        ->assertSee('name="email"', false);
});
