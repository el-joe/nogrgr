<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

/*
| Component library endpoints: guests are sent to the admin login (JSON: 401),
| admins without system.ui-kit.view get 403, the demo endpoints honour the JSON contract.
*/

function uiKitAccessAdmin(bool $withPermission = true): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $withPermission ? $admin->assignRole('super-admin') : $admin->givePermissionTo('dashboard.view');

    return $admin;
}

dataset('ui-kit endpoints', [
    'page' => ['get', '/admin/ui-kit'],
    'table' => ['get', '/admin/ui-kit/table'],
    'bulk' => ['post', '/admin/ui-kit/bulk'],
    'form' => ['post', '/admin/ui-kit/form'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $this->{$method}($this->centralUrl($path))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('ui-kit endpoints');

it('answers 401 to guest JSON requests', function (string $method, string $path): void {
    $this->{$method.'Json'}($this->centralUrl($path))->assertUnauthorized();
})->with('ui-kit endpoints');

it('forbids admins without system.ui-kit.view', function (string $method, string $path): void {
    $this->actingAs(uiKitAccessAdmin(false), 'admin')
        ->{$method.'Json'}($this->centralUrl($path))
        ->assertForbidden();
})->with('ui-kit endpoints');

it('does not let another guard reach the component library', function (): void {
    $tenant = $this->createTenant('acme');
    $owner = $this->createVendorOwner($tenant);

    $this->actingAsVendor($owner, $tenant)
        ->get($this->centralUrl('/admin/ui-kit'))
        ->assertRedirect($this->centralUrl('/admin/login'));
});

it('accepts a valid validation-demo submission', function (): void {
    $this->actingAs(uiKitAccessAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/form'), [
            'name' => 'Desk lamp',
            'email' => 'qa@example.test',
            'category' => 'lighting',
            'countries' => ['SA'],
            'phone' => '+966501234567',
            'start_date' => '2026-11-24',
            'description' => 'Warm light.',
            'terms' => '1',
            'title' => ['en' => 'Desk lamp', 'ar' => 'مصباح مكتب'],
        ])
        ->assertOk()
        ->assertExactJson(['success' => true, 'message' => __('platform::ui-kit.form_saved')]);
});

it('returns translated 422 messages in Arabic (QA-P0-005)', function (): void {
    $response = $this->actingAs(uiKitAccessAdmin(), 'admin')
        ->withCredentials()
        ->withUnencryptedCookie('locale', 'ar')
        ->postJson($this->centralUrl('/admin/ui-kit/form'), ['email' => 'nope', 'start_date' => '24/11/2026'])
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['name', 'email', 'start_date', 'title.ar']]);

    expect($response->json('errors.name.0'))->not->toBe(__('validation.required', ['attribute' => 'name'], 'en'))
        ->and($response->json('errors.name.0'))->toBe('حقل الاسم مطلوب.')
        ->and($response->json('errors')['title.en'][0])->toBe('حقل العنوان (الإنجليزية) مطلوب.')
        ->and($response->json('errors')['title.ar'][0])->toBe('حقل العنوان (العربية) مطلوب.');
});

it('names per-locale fields in English too (QA-P0-005)', function (): void {
    $response = $this->actingAs(uiKitAccessAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/form'), [])
        ->assertUnprocessable();

    expect($response->json('errors')['title.en'][0])->toBe('The title (English) field is required.');
});

it('pages, filters and searches the sample data table', function (): void {
    $admin = uiKitAccessAdmin();

    $all = $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/ui-kit/table?draw=1&start=0&length=5'))
        ->assertOk()
        ->assertJsonPath('draw', 1)
        ->json();

    expect($all['data'])->toHaveCount(min(5, $all['recordsTotal']))
        ->and($all['recordsTotal'])->toBeGreaterThan(5);

    $second = $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/ui-kit/table?draw=2&start=5&length=5'))
        ->assertOk()
        ->json();

    expect($second['data'][0]['sku'] ?? null)->not->toBe($all['data'][0]['sku'] ?? null);
});

it('accepts the standard DataTables search parameter shape (QA-P0-002)', function (): void {
    $this->actingAs(uiKitAccessAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/ui-kit/table?draw=1&start=0&length=10&search[value]=&search[regex]=false'))
        ->assertOk()
        ->assertJsonPath('draw', 1);

    $this->actingAs(uiKitAccessAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/ui-kit/table?draw=1&start=0&length=10&search[value]=zzzz-no-match&search[regex]=false'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', 0);
});

it('reports the bulk action count', function (): void {
    $this->actingAs(uiKitAccessAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/ui-kit/bulk'), ['ids' => [1, 2, 3]])
        ->assertOk()
        ->assertJsonPath('message', trans_choice('platform::ui-kit.bulk_done', 3, ['count' => 3]));
});
