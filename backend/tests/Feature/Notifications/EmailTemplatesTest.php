<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Events\EmailTemplatesSynced;
use App\Modules\Notifications\Jobs\SyncEmailTemplatesToTenants;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
| Admin › Settings › Email templates (ADM-102/102a): access, list JSON + filters,
| one row per registry key (#124), edit per locale with sanitised bodies and known
| variables only, preview, send test, sync to tenants (queued job → EmailTemplatesSynced).
*/

function emailTemplatesAdmin(string ...$permissions): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['dashboard.view', ...$permissions]);

    return $admin;
}

dataset('email template endpoints', [
    'index' => ['get', '/admin/settings/email-templates', 'email-templates.view'],
    'data' => ['get', '/admin/settings/email-templates/data', 'email-templates.view'],
    'preview' => ['get', '/admin/settings/email-templates/{id}/preview', 'email-templates.view'],
    'edit' => ['get', '/admin/settings/email-templates/{id}/edit', 'email-templates.manage'],
    'update' => ['put', '/admin/settings/email-templates/{id}', 'email-templates.manage'],
    'render' => ['post', '/admin/settings/email-templates/{id}/render', 'email-templates.manage'],
    'reset' => ['post', '/admin/settings/email-templates/{id}/reset', 'email-templates.manage'],
    'test' => ['post', '/admin/settings/email-templates/{id}/test', 'email-templates.manage'],
    'sync' => ['post', '/admin/settings/email-templates/{id}/sync', 'email-templates.manage'],
    'sync all' => ['post', '/admin/settings/email-templates/sync', 'email-templates.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $template = EmailTemplate::factory()->create();

    $this->{$method}($this->centralUrl(str_replace('{id}', (string) $template->id, $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('email template endpoints');

it('forbids admins without the email templates permission', function (string $method, string $path, string $permission): void {
    $template = EmailTemplate::factory()->create();
    $admin = $permission === 'email-templates.manage' ? emailTemplatesAdmin('email-templates.view') : emailTemplatesAdmin();

    $this->actingAs($admin, 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) $template->id, $path)))
        ->assertForbidden();
})->with('email template endpoints');

it('lists templates with audience tabs and filters', function (): void {
    EmailTemplate::factory()->create();
    EmailTemplate::factory()->event(EmailTemplateEvent::VendorWelcome)->create(['subject' => ['en' => 'Welcome'], 'body' => ['en' => '<p>Hi</p>']]);
    $admin = emailTemplatesAdmin('email-templates.manage');

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/settings/email-templates?tab=vendors'))
        ->assertOk()
        ->assertSee(route('admin.settings.email-templates.data', ['audience' => 'vendors']), false)
        ->assertSee(route('admin.settings.email-templates.sync-all'), false);

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/settings/email-templates/data?draw=1&start=0&length=10&audience=vendors'))
        ->assertOk()
        ->assertJsonPath('recordsFiltered', count(EmailTemplateEvent::forAudience(EmailAudience::Vendors)));

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/settings/email-templates/data?draw=1&start=0&length=10&language=missing_ar'))
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.event', 'vendor.welcome');

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/settings/email-templates/data?draw=1&start=0&length=10&search[value]=order.ship'))
        ->assertJsonPath('recordsFiltered', 1);
});

it('saves a template with a sanitised body', function (): void {
    $template = EmailTemplate::factory()->create();
    $admin = emailTemplatesAdmin('email-templates.manage');

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), [
            'status' => 'active',
            'subject' => ['en' => 'Your order {{ order.number }} is on its way', 'ar' => 'طلبك {{ order.number }} في الطريق'],
            'body' => [
                'en' => '<h2 onclick="x()">Good news, {{ customer.first_name }}!</h2><script>alert(1)</script><p><a href="javascript:alert(1)">x</a> <a href="{{ shipment.tracking_url }}">Track</a></p>',
                'ar' => '<p>أخبار سارة</p>',
            ],
        ])
        ->assertOk();

    $body = $template->refresh()->getTranslation('body', 'en');

    expect($template->event)->toBe(EmailTemplateEvent::OrderShipped)
        ->and($template->audience->value)->toBe('shoppers')
        ->and($body)->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:')
        ->toContain('<h2>Good news, {{ customer.first_name }}!</h2>')
        ->toContain('href="{{ shipment.tracking_url }}"');
});

it('validates template input', function (): void {
    $template = EmailTemplate::factory()->create();

    $this->actingAs(emailTemplatesAdmin('email-templates.manage'), 'admin')
        ->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), [
            'status' => 'draft',
            'subject' => ['en' => ''], 'body' => ['en' => '<script>only</script>', 'ar' => '<p>نص</p>'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'subject.en', 'body.en', 'subject.ar']);

    $this->actingAs(emailTemplatesAdmin('email-templates.manage'), 'admin')
        ->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), [
            'status' => 'active',
            'subject' => ['en' => 'Hi {{ customer.password }}'], 'body' => ['en' => '<p>{{ order.number }}</p>'],
        ])
        ->assertJsonValidationErrors('subject.en');
});

it('edits a template, keeping the event fixed', function (): void {
    $template = EmailTemplate::factory()->create();
    $admin = emailTemplatesAdmin('email-templates.manage');

    $this->actingAs($admin, 'admin')->get($this->centralUrl("/admin/settings/email-templates/{$template->id}/edit"))->assertOk()->assertSee('Good news');

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), [
            'status' => 'inactive',
            'subject' => ['en' => 'Shipped {{ order.number }}', 'ar' => ''],
            'body' => ['en' => '<p>On its way</p>', 'ar' => ''],
        ])
        ->assertOk();

    $template->refresh();
    expect($template->status)->toBe(EmailTemplateStatus::Inactive)
        ->and($template->getTranslation('subject', 'en'))->toBe('Shipped {{ order.number }}')
        ->and($template->missingLocales())->toBe(['ar']);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"), ['event' => 'vendor.welcome', 'status' => 'active', 'subject' => ['en' => 'x'], 'body' => ['en' => '<p>x</p>']])
        ->assertJsonValidationErrors('event');
});

it('previews a template with escaped sample values', function (): void {
    $template = EmailTemplate::factory()->create();

    $this->actingAs(emailTemplatesAdmin('email-templates.view'), 'admin')
        ->get($this->centralUrl("/admin/settings/email-templates/{$template->id}/preview?locale=ar"))
        ->assertOk()
        ->assertHeader('Content-Security-Policy')
        ->assertSee('أخبار سارة يا Ahmed!', false)
        ->assertSee('dir="rtl"', false);
});

it('sends a test email in the chosen locale', function (): void {
    Mail::fake();
    $template = EmailTemplate::factory()->create();
    $admin = emailTemplatesAdmin('email-templates.manage');

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/test"), ['email' => 'qa@nogrgr.test', 'locale' => 'en'])
        ->assertOk();

    Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail): bool => $mail->hasTo($admin->email) && ! $mail->hasTo('qa@nogrgr.test')
        && $mail->email->subject === 'Your order ORD-10431 is on its way'
        && str_contains($mail->email->html, 'Good news, Ahmed!'));

    $this->actingAs(emailTemplatesAdmin('email-templates.manage'), 'admin')
        ->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/test"), ['locale' => 'fr'])
        ->assertJsonValidationErrors(['locale']);
});

it('queues the sync to tenants and publishes the templates', function (): void {
    Queue::fake();
    $template = EmailTemplate::factory()->create();
    $admin = emailTemplatesAdmin('email-templates.manage');

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl("/admin/settings/email-templates/{$template->id}/sync"))->assertOk();
    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/settings/email-templates/sync'))->assertOk();

    Queue::assertPushed(SyncEmailTemplatesToTenants::class, fn (SyncEmailTemplatesToTenants $job): bool => $job->templateIds === [$template->id]);
    Queue::assertPushed(SyncEmailTemplatesToTenants::class, fn (SyncEmailTemplatesToTenants $job): bool => $job->templateIds === []);

    Event::fake([EmailTemplatesSynced::class]);
    app()->call([new SyncEmailTemplatesToTenants, 'handle']);

    Event::assertDispatched(EmailTemplatesSynced::class, fn (EmailTemplatesSynced $event): bool => $event->templates[0]['event'] === 'order.shipped'
        && $event->templates[0]['subject']['ar'] === 'طلبك {{ order.number }} في الطريق');
    expect($template->refresh()->synced_at)->not->toBeNull();
});

it('has no create or delete endpoints: every registry key keeps its row', function (): void {
    $template = EmailTemplate::factory()->create();
    $admin = emailTemplatesAdmin('email-templates.manage');

    $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/settings/email-templates/create'))->assertNotFound();
    $this->actingAs($admin, 'admin')->deleteJson($this->centralUrl("/admin/settings/email-templates/{$template->id}"))->assertStatus(405);

    expect(EmailTemplate::query()->count())->toBe(1);
});

it('renders active templates for an event and skips inactive ones', function (): void {
    $renderer = app(EmailTemplateRenderer::class);
    expect($renderer->renderEvent(EmailTemplateEvent::OrderShipped, 'en', ['order.number' => 'ORD-1'])->subject)->toBe('Your order ORD-1 is on its way');

    $template = EmailTemplate::factory()->create(['subject' => ['en' => 'Hi <b>{{ customer.first_name }}</b>'], 'body' => ['en' => '<p>{{ customer.first_name }}</p>']]);
    $rendered = $renderer->renderEvent(EmailTemplateEvent::OrderShipped, 'ar', ['customer.first_name' => '<script>x</script>']);

    expect($rendered->locale)->toBe('ar')
        ->and($rendered->html)->toContain('&lt;script&gt;x&lt;/script&gt;')->not->toContain('<script>x');

    $template->update(['status' => EmailTemplateStatus::Inactive]);
    expect($renderer->renderEvent(EmailTemplateEvent::OrderShipped, 'en', []))->toBeNull();
});

it('sanitises editor HTML to the allow-list', function (string $input, string $expected): void {
    expect(EmailHtmlSanitizer::clean($input))->toBe($expected);
})->with([
    'unwraps unknown tags' => ['<section><p>Hi</p></section>', '<p>Hi</p>'],
    'drops scripts' => ['<p>a</p><script>b</script><style>p{}</style>', '<p>a</p>'],
    'strips event handlers' => ['<p onclick="x" class="c">a</p>', '<p>a</p>'],
    'keeps safe links' => ['<a href="https://x.test" target="_blank">x</a>', '<a href="https://x.test">x</a>'],
    'removes js links' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
    'keeps image' => ['<img src="https://x.test/a.png" alt="a" onerror="x">', '<img src="https://x.test/a.png" alt="a">'],
]);
