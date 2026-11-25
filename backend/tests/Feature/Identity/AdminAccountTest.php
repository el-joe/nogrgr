<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Notifications\AdminPasswordChangedNotification;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/AdminTestHelpers.php';

/*
| Admin › My profile (ADM-005): profile fields, avatar, language, timezone and the
| password change that requires the current password.
*/

dataset('account endpoints', [
    'edit' => ['get', '/admin/account'],
    'update' => ['put', '/admin/account'],
    'password' => ['put', '/admin/account/password'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $this->{$method}($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
})->with('account endpoints');

it('shows the profile page to any signed-in admin, linked from the user menu', function (): void {
    $admin = adminWith([], ['name' => 'Sara Admin']);

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/account'))
        ->assertOk()
        ->assertSee('Sara Admin')
        ->assertSee(route('admin.account.update'), false)
        ->assertSee(route('admin.account.password'), false)
        ->assertSee(route('admin.account.edit'), false);
});

it('updates name, email, language, timezone and avatar', function (): void {
    Storage::fake('public');
    $admin = adminWith([]);

    $this->actingAs($admin, 'admin')
        ->put($this->centralUrl('/admin/account'), [
            'name' => 'Sara Updated',
            'email' => 'SARA@example.test',
            'locale' => 'en',
            'timezone' => 'Asia/Riyadh',
            'avatar' => [UploadedFile::fake()->image('me.png', 200, 200)],
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $admin->refresh();

    expect($admin->name)->toBe('Sara Updated')
        ->and($admin->email)->toBe('sara@example.test')
        ->and($admin->timezone)->toBe('Asia/Riyadh')
        ->and($admin->avatar_path)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'admin.updated')->where('subject_id', (string) $admin->id)->exists())->toBeTrue();

    Storage::disk('public')->assertExists((string) $admin->avatar_path);
    $old = (string) $admin->avatar_path;

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/account'), ['name' => 'Sara Updated', 'email' => 'sara@example.test', 'locale' => 'en', 'timezone' => 'Asia/Riyadh'])
        ->assertOk();

    expect($admin->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($old);
});

it('switches the panel language when the profile language changes', function (): void {
    $admin = adminWith([], ['locale' => 'en']);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/account'), ['name' => $admin->name, 'email' => $admin->email, 'locale' => 'ar', 'timezone' => 'UTC'])
        ->assertOk()
        ->assertJsonPath('redirect', route('admin.account.edit'))
        ->assertCookie('locale', 'ar', false);
});

it('validates the profile', function (): void {
    $other = Admin::factory()->create();
    $admin = adminWith([]);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/account'), ['name' => '', 'email' => $other->email, 'locale' => 'xx', 'timezone' => 'Mars/Base'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'locale', 'timezone']);
});

it('changes the password only with the current one and a strong new one', function (): void {
    Notification::fake();
    $admin = adminWith([]);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/account/password'), ['current_password' => 'wrong', 'password' => 'N3w!Password123', 'password_confirmation' => 'N3w!Password123'])
        ->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/account/password'), ['current_password' => 'password', 'password' => 'weakpass', 'password_confirmation' => 'weakpass'])
        ->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/account/password'), ['current_password' => 'password', 'password' => 'N3w!Password123', 'password_confirmation' => 'N3w!Password123'])
        ->assertOk();

    expect(Hash::check('N3w!Password123', (string) $admin->refresh()->password))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'auth.password_changed')->where('subject_id', (string) $admin->id)->exists())->toBeTrue();

    Notification::assertSentTo($admin, AdminPasswordChangedNotification::class);
});
