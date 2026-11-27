<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Notifications\AdminPasswordChangedNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use App\Modules\Notifications\Services\MailLayoutService;
use App\Modules\Notifications\Support\MailBodyStyler;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Decision #118 e-mail framework: registry defaults rendered through the MAIL-001 layout
| (central + store variants, RTL), Admin › Email templates (registry rows, live preview,
| send test to the current admin, reset to default, enable / disable, last edited),
| Admin › Email layout, Vendor › Email templates overrides (precedence, preview, test,
| variable checks) and the enable switch applied to notifications.
*/

function emailSystemAdmin(string ...$permissions): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create(['name' => 'Laila Admin']);
    $admin->givePermissionTo(['dashboard.view', ...$permissions]);

    return $admin;
}

it('renders a registry default through the branded layout in English and Arabic (RTL)', function (): void {
    $renderer = app(EmailTemplateRenderer::class);
    $variables = EmailTemplateEvent::OrderConfirmation->variables();

    $en = $renderer->renderDefault(EmailTemplateEvent::OrderConfirmation, 'en', $variables);
    $ar = $renderer->renderDefault(EmailTemplateEvent::OrderConfirmation, 'ar', $variables);

    expect($en->subject)->toBe('Order ORD-10431 confirmed')
        ->and($en->html)->toContain('dir="ltr"')->toContain('Thank you for shopping at Megz Store')
        ->toContain('prefers-color-scheme: dark')->toContain('display:none;max-height:0')
        ->toContain('href="https://megz.example.com/account/orders/10431"')
        ->and($ar->subject)->toBe('تم تأكيد الطلب ORD-10431')
        ->and($ar->html)->toContain('<html lang="ar" dir="rtl"')->toContain('text-align:right');
});

it('drops links whose variable is empty and draws a lone link as a button', function (): void {
    $html = MailBodyStyler::style('<p>Hi</p><p><a href="">Open</a></p><p><a href="https://x.test/a">Go</a></p><p><a href="javascript:alert(1)">Bad</a></p>', '#ff5a2c');

    expect($html)->not->toContain('>Open<')->not->toContain('javascript')
        ->toContain('background:#ff5a2c')->toContain('href="https://x.test/a"');
});

it('escapes variable values and keeps unknown placeholders empty', function (): void {
    $rendered = app(EmailTemplateRenderer::class)->renderContent('Hi {{ customer.name }} {{ nope }}', '<p>{{ customer.name }}</p>', 'en', ['customer.name' => '<b>Sara</b>']);

    expect($rendered->subject)->toBe('Hi <b>Sara</b>')
        ->and($rendered->html)->toContain('&lt;b&gt;Sara&lt;/b&gt;')->not->toContain('<b>Sara</b>');
});

it('lists every registry e-mail, previews unsaved content, resets to default and records the editor', function (): void {
    $admin = emailSystemAdmin('email-templates.view', 'email-templates.manage');
    $this->actingAs($admin, 'admin');

    $this->get($this->centralUrl('/admin/settings/email-templates'))->assertOk()->assertSee(__('notifications::templates.layout'));
    expect(EmailTemplate::query()->count())->toBe(count(EmailTemplateEvent::cases()));

    $template = EmailTemplate::query()->where('event', EmailTemplateEvent::VendorWelcome->value)->firstOrFail();
    $this->get($this->centralUrl("/admin/settings/email-templates/{$template->id}/edit"))->assertOk()
        ->assertSee('{{ login.url }}', false)->assertSee(EmailTemplateEvent::describeVariable('login.url'))
        ->assertSee(__('notifications::templates.never_edited'));

    $this->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/render"), [
        'locale' => 'ar', 'subject' => ['ar' => 'أهلًا {{ vendor.name }}'], 'body' => ['ar' => '<p>متجرك {{ store.name }}</p><script>x</script>'],
    ])->assertOk()->assertJsonPath('subject', 'أهلًا Mona Khaled')
        ->assertJson(fn ($json) => $json->where('html', fn (string $html): bool => str_contains($html, 'متجرك Megz Store') && ! str_contains($html, '<script') && str_contains($html, 'dir="rtl"'))->etc());

    $this->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), [
        'status' => 'inactive',
        'subject' => ['en' => 'Hello {{ vendor.name }}', 'ar' => ''],
        'body' => ['en' => '<p>Custom</p>', 'ar' => ''],
    ])->assertOk();
    $template->refresh();
    expect($template->updated_by_name)->toBe('Laila Admin')->and($template->status)->toBe(EmailTemplateStatus::Inactive);

    $this->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), [
        'status' => 'active', 'subject' => ['en' => 'Hi {{ vendor.secret }}'], 'body' => ['en' => '<p>x</p>'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['subject.en']);

    $this->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/reset"))->assertOk();
    $template->refresh();
    expect($template->getTranslation('subject', 'en'))->toBe(EmailTemplateEvent::VendorWelcome->defaultSubject('en'))
        ->and($template->getTranslation('body', 'ar'))->toBe(EmailTemplateEvent::VendorWelcome->defaultBody('ar'));
});

it('sends the test e-mail to the signed-in admin only', function (): void {
    Mail::fake();
    $admin = emailSystemAdmin('email-templates.view', 'email-templates.manage');
    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/settings/email-templates'))->assertOk();
    $template = EmailTemplate::query()->where('event', EmailTemplateEvent::AdminAlert->value)->firstOrFail();

    $this->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/test"), ['locale' => 'ar', 'email' => 'someone@else.test'])
        ->assertOk()->assertJsonPath('message', __('notifications::templates.test_sent', ['email' => $admin->email]));

    Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail): bool => $mail->hasTo($admin->email) && ! $mail->hasTo('someone@else.test') && $mail->email->locale === 'ar');
});

it('guards the layout and the new template endpoints with the email template permissions', function (): void {
    $viewer = emailSystemAdmin('email-templates.view');
    $template = EmailTemplate::factory()->create();
    $this->actingAs($viewer, 'admin');

    $this->get($this->centralUrl('/admin/settings/email-layout'))->assertOk();
    $this->putJson($this->centralUrl('/admin/settings/email-layout'), ['primary_color' => '#112233', 'background_color' => '#ffffff'])->assertForbidden();
    $this->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/reset"))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/render"), ['locale' => 'en'])->assertForbidden();
});

it('saves the e-mail layout and applies it to every central e-mail', function (): void {
    Storage::fake('public');
    $admin = emailSystemAdmin('email-templates.view', 'email-templates.manage');
    $this->actingAs($admin, 'admin');

    $this->putJson($this->centralUrl('/admin/settings/email-layout'), ['primary_color' => 'red', 'background_color' => '#fff'])
        ->assertUnprocessable()->assertJsonValidationErrors(['primary_color', 'background_color']);

    $this->put($this->centralUrl('/admin/settings/email-layout'), [
        'primary_color' => '#123ABC',
        'background_color' => '#EEEEEE',
        'footer' => ['en' => 'Nogrgr LLC footer', 'ar' => 'تذييل نوجرجر'],
        'address' => 'Riyadh, Saudi Arabia',
        'help_url' => 'https://help.example.com',
        'privacy_url' => 'https://example.com/privacy',
        'show_socials' => '1',
        'socials' => ['instagram' => 'https://instagram.com/nogrgr'],
        'logo' => [UploadedFile::fake()->image('mail-logo.png', 200, 60)],
    ], ['Accept' => 'application/json'])->assertOk();

    $settings = app(MailLayoutService::class)->current();
    expect($settings['primary_color'])->toBe('#123abc')->and($settings['has_own_logo'])->toBeTrue();

    $en = app(EmailTemplateRenderer::class)->renderDefault(EmailTemplateEvent::AdminAlert, 'en', EmailTemplateEvent::AdminAlert->variables());
    $ar = app(EmailTemplateRenderer::class)->renderDefault(EmailTemplateEvent::AdminAlert, 'ar', EmailTemplateEvent::AdminAlert->variables());

    expect($en->html)->toContain('#123abc')->toContain('#eeeeee')->toContain('Nogrgr LLC footer')->toContain('Riyadh, Saudi Arabia')
        ->toContain('https://instagram.com/nogrgr')->toContain('https://help.example.com')->toContain('branding/mail/')
        ->and($ar->html)->toContain('تذييل نوجرجر')->toContain(__('notifications::mail_layout.links.privacy', [], 'ar'));

    $this->get($this->centralUrl('/admin/settings/email-layout/preview?locale=ar'))->assertOk()
        ->assertHeader('Content-Security-Policy')->assertSee('dir="rtl"', false);
});

it('does not mail a notification whose template is disabled but keeps the inbox row', function (): void {
    $admin = emailSystemAdmin('dashboard.view');
    $template = EmailTemplate::factory()->event(EmailTemplateEvent::AdminAlert)->create(['status' => EmailTemplateStatus::Inactive]);
    $sent = [];
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sent): void {
        $sent[] = $event->channel;
    });

    $admin->notifyNow(new AdminPasswordChangedNotification('10:00'), ['database', 'mail']);

    expect($sent)->toBe(['database'])->and($admin->notifications()->count())->toBe(1);

    $template->update(['status' => EmailTemplateStatus::Active]);
    $admin->notifyNow(new AdminPasswordChangedNotification('10:00'), ['mail']);

    expect($sent)->toBe(['database', 'mail']);
});

describe('store templates', function (): void {
    beforeEach(function (): void {
        $this->tenant = $this->createTenant('ets-'.Str::lower(Str::random(6)), ['name' => 'Megz']);
        $this->owner = $this->createVendorOwner($this->tenant);
        $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/email-templates'.$path);
    });

    it('lists every store-scoped e-mail and starts the editor from the code default', function (): void {
        $this->actingAsVendor($this->owner, $this->tenant);

        $ids = collect($this->getJson(($this->url)('/data?draw=1&start=0&length=50'))->assertOk()->json('data'))->pluck('id')->all();
        expect($ids)->toEqualCanonicalizing(array_map(static fn (EmailTemplateEvent $event): string => $event->value, EmailTemplateEvent::storeScoped()));

        $this->get(($this->url)('/order.shipped/edit'))->assertOk()
            ->assertSee(EmailTemplateEvent::OrderShipped->defaultSubject('en'), false)
            ->assertSee('{{ shipment.tracking_url }}', false);
    });

    it('prefers the store version over the admin version over the default, and honours the store switch', function (): void {
        EmailTemplate::factory()->event(EmailTemplateEvent::OrderShipped)->create([
            'subject' => ['en' => 'Platform shipped {{ order.number }}', 'ar' => 'منصة {{ order.number }}'],
            'body' => ['en' => '<p>Platform</p>', 'ar' => '<p>منصة</p>'],
        ]);
        $renderer = fn () => $this->tenant->run(fn () => app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::OrderShipped, 'en', ['order.number' => 'ORD-9']));

        expect($renderer()->subject)->toBe('Platform shipped ORD-9');

        $this->actingAsVendor($this->owner, $this->tenant);
        $this->putJson(($this->url)('/order.shipped'), [
            'status' => 'active',
            'subject' => ['en' => 'Megz shipped {{ order.number }}'],
            'body' => ['en' => '<p>Store body</p>'],
        ])->assertOk();

        $rendered = $renderer();
        expect($rendered->subject)->toBe('Megz shipped ORD-9')->and($rendered->html)->toContain('Store body')->toContain('Megz')
            ->and($this->tenant->run(fn () => StoreEmailTemplate::query()->where('event', 'order.shipped')->value('updated_by_name')))->toBe($this->owner->name);

        // Arabic is not customised: the platform's Arabic text is used.
        expect($this->tenant->run(fn () => app(EmailTemplateRenderer::class)->renderEvent(EmailTemplateEvent::OrderShipped, 'ar', ['order.number' => 'ORD-9']))->subject)->toBe('منصة ORD-9');

        $this->putJson(($this->url)('/order.shipped'), ['status' => 'inactive', 'subject' => ['en' => 'Off'], 'body' => ['en' => '<p>Off</p>']])->assertOk();
        expect($renderer())->toBeNull();
    });

    it('rejects undeclared variables, previews unsaved content and sends the test to the member', function (): void {
        Mail::fake();
        $this->actingAsVendor($this->owner, $this->tenant);

        $this->putJson(($this->url)('/order.confirmation'), ['subject' => ['en' => 'Hi {{ admin.password }}'], 'body' => ['en' => '<p>{{ order.number }}</p>']])
            ->assertUnprocessable()->assertJsonValidationErrors(['subject.en']);

        $this->postJson(($this->url)('/order.confirmation/render'), ['locale' => 'en', 'subject' => ['en' => 'Draft {{ order.number }}'], 'body' => ['en' => '<p>Draft for {{ store.name }}</p>']])
            ->assertOk()->assertJsonPath('subject', 'Draft ORD-10431');

        $this->postJson(($this->url)('/order.confirmation/test'), ['locale' => 'en'])->assertOk();
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail): bool => $mail->hasTo($this->owner->email) && str_contains($mail->email->html, 'Megz'));
    });
});
