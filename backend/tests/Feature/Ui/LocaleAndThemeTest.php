<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;

/*
| Panels render server-side in the right direction and theme: `dir`/`lang` on
| <html> from ?lang= or the locale cookie, `.dark` from the theme cookie.
*/

function localeAdmin(): Admin
{
    test()->seed(CentralPermissionSeeder::class);

    return tap(Admin::factory()->create())->assignRole('super-admin');
}

it('renders admin pages RTL in Arabic and LTR in English', function (string $path, string $lang, string $dir): void {
    $this->actingAs(localeAdmin(), 'admin')
        ->get($this->centralUrl($path.'?lang='.$lang))
        ->assertOk()
        ->assertSee('lang="'.$lang.'"', false)
        ->assertSee('dir="'.$dir.'"', false);
})->with(['/admin', '/admin/ui-kit'])->with([['ar', 'rtl'], ['en', 'ltr']]);

it('renders the sign-in pages RTL in Arabic', function (string $path): void {
    $this->get($this->centralUrl($path.'?lang=ar'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('identity::auth.login', [], 'ar'));
})->with(['/admin/login', '/affiliate/login', '/tenant/login']);

it('remembers the language in the locale cookie for later requests', function (): void {
    $admin = localeAdmin();

    $this->actingAs($admin, 'admin')
        ->withUnencryptedCookie('locale', 'ar')
        ->get($this->centralUrl('/admin'))
        ->assertOk()
        ->assertSee('dir="rtl"', false);
});

it('ignores unsupported languages and falls back to English LTR', function (): void {
    $this->get($this->centralUrl('/admin/login?lang=xx'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertCookieMissing('locale');

    $this->withUnencryptedCookie('locale', '../../etc')
        ->get($this->centralUrl('/admin/login'))
        ->assertOk()
        ->assertSee('dir="ltr"', false);
});

it('renders the dark theme from the theme cookie and light otherwise', function (): void {
    $admin = localeAdmin();

    $this->actingAs($admin, 'admin')
        ->withUnencryptedCookie('theme', 'dark')
        ->get($this->centralUrl('/admin/ui-kit'))
        ->assertOk()
        ->assertSee('class="dark"', false);

    $this->actingAs($admin, 'admin')
        ->withUnencryptedCookie('theme', 'light')
        ->get($this->centralUrl('/admin/ui-kit'))
        ->assertOk()
        ->assertDontSee('class="dark"', false);
});

it('ships the theme toggle and the language switch in the admin shell', function (): void {
    $this->actingAs(localeAdmin(), 'admin')
        ->get($this->centralUrl('/admin'))
        ->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee('lang=ar', false)
        ->assertSee('data-sidebar', false);
});
