<?php

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Mail\IntegrationTestMail;
use App\Modules\Integrations\Models\IntegrationSetting;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Integrations\Services\IntegrationConfigOverlay;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/IntegrationsHelpers.php';

/*
| Admin › Integrations settings tabs (ADM-100d/e/f/h/j + AI translation): access,
| validation, encrypted + masked secrets, runtime config overlay, connection tests
| (Http::fake / Mail::fake) with typed errors.
*/

dataset('integration endpoints', [
    'index' => ['get', '/admin/integrations', 'integrations.view'],
    'show' => ['get', '/admin/integrations/mail', 'integrations.view'],
    'update' => ['put', '/admin/integrations/mail', 'integrations.manage'],
    'test' => ['post', '/admin/integrations/cloudflare/test', 'integrations.manage'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $this->{$method}($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
})->with('integration endpoints');

it('forbids admins without the integrations permission', function (string $method, string $path, string $permission): void {
    $admin = $permission === 'integrations.manage' ? integrationsAdmin('integrations.view') : integrationsAdmin();

    $this->actingAs($admin, 'admin')->{$method.'Json'}($this->centralUrl($path))->assertForbidden();
})->with('integration endpoints');

it('redirects the integrations index to the payment gateways tab', function (): void {
    $this->actingAs(integrationsAdmin('integrations.view'), 'admin')
        ->get($this->centralUrl('/admin/integrations'))
        ->assertRedirect(route('admin.integrations.gateways.index'));
});

it('renders every settings tab with the tab bar', function (IntegrationSection $section): void {
    $this->actingAs(integrationsAdmin('integrations.manage'), 'admin')
        ->get($this->centralUrl('/admin/integrations/'.$section->value))
        ->assertOk()
        ->assertSee(__('integrations::admin.sections.'.$section->value.'.title'))
        ->assertSee(route('admin.integrations.gateways.index'), false)
        ->assertSee(route('admin.integrations.update', $section), false);
})->with(IntegrationSection::cases());

it('validates required fields only while a card is enabled', function (): void {
    $admin = integrationsAdmin('integrations.manage');

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/cloudflare'), ['enabled' => '1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'api_token', 'zone_id', 'zone_name']);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/cloudflare'), ['enabled' => '0'])
        ->assertOk();

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/mail'), ['enabled' => '1', 'mailer' => 'smtp', 'host' => 'smtp.test', 'port' => 'abc', 'encryption' => 'weird', 'from_name' => 'N', 'from_address' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['port', 'encryption', 'from_address']);
});

it('stores secrets encrypted, never returns them and keeps them when left blank', function (): void {
    $admin = integrationsAdmin('integrations.manage');
    $token = 'cf-token-SUPERSECRET-9qLx9';
    $payload = ['enabled' => '1', 'account_id' => 'acc-1', 'api_token' => $token, 'zone_id' => 'zone-1', 'zone_name' => 'stores.example.com', 'ssl_for_saas' => '1'];

    $save = $this->actingAs($admin, 'admin')->putJson($this->centralUrl('/admin/integrations/cloudflare'), $payload)->assertOk();
    expect($save->getContent())->not->toContain($token);

    $row = IntegrationSetting::query()->where('group', 'cloudflare')->where('key', 'api_token')->sole();
    expect($row->is_secret)->toBeTrue()
        ->and($row->getAttributes()['value'])->not->toContain('SUPERSECRET');

    $page = $this->actingAs($admin, 'admin')->get($this->centralUrl('/admin/integrations/cloudflare'))->assertOk();
    expect($page->getContent())->not->toContain($token)->toContain('qLx9');

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/cloudflare'), [...$payload, 'api_token' => '', 'zone_name' => 'shops.example.com'])
        ->assertOk();

    $settings = app(SettingsRepositoryContract::class);
    $settings->refresh();
    expect($settings->get('cloudflare', 'api_token'))->toBe($token)
        ->and($settings->get('cloudflare', 'zone_name'))->toBe('shops.example.com')
        ->and(config('services.cloudflare.zone_name'))->toBe('shops.example.com');
});

it('overlays saved mail settings on the runtime config', function (): void {
    $this->actingAs(integrationsAdmin('integrations.manage'), 'admin')
        ->putJson($this->centralUrl('/admin/integrations/mail'), [
            'enabled' => '1', 'mailer' => 'smtp', 'host' => 'smtp.mailer.test', 'port' => '465', 'encryption' => 'ssl',
            'username' => 'postmaster', 'password' => 'mail-pass-123456', 'from_name' => 'Nogrgr', 'from_address' => 'no-reply@nogrgr.test',
        ])
        ->assertOk();

    config(['mail.mailers.smtp.host' => 'env-host']);
    app(IntegrationConfigOverlay::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.mailer.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.password'))->toBe('mail-pass-123456')
        ->and(config('mail.from.address'))->toBe('no-reply@nogrgr.test');
});

it('sends a test email through the saved mail settings', function (): void {
    Mail::fake();
    $admin = integrationsAdmin('integrations.manage');
    app(SettingsRepositoryContract::class)->put('mail', ['enabled' => true, 'mailer' => 'log', 'from_name' => 'Nogrgr', 'from_address' => 'no-reply@nogrgr.test']);

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/mail/test'), ['email' => 'ops@nogrgr.test'])
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    Mail::assertSent(IntegrationTestMail::class, fn (IntegrationTestMail $mail): bool => $mail->hasTo('ops@nogrgr.test'));

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/mail/test'), ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('reports an unreachable SMTP server as a typed error', function (): void {
    app(SettingsRepositoryContract::class)->put('mail', ['enabled' => true, 'mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'from_name' => 'N', 'from_address' => 'no-reply@nogrgr.test']);

    $this->actingAs(integrationsAdmin('integrations.manage'), 'admin')
        ->postJson($this->centralUrl('/admin/integrations/mail/test'), ['email' => 'ops@nogrgr.test'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.error', 'unreachable');
});

it('tests Cloudflare: token + zone, and reports rejected tokens', function (): void {
    $admin = integrationsAdmin('integrations.manage');
    app(SettingsRepositoryContract::class)->put('cloudflare', ['enabled' => true, 'account_id' => 'a', 'api_token' => 'tok-123456789012', 'zone_id' => 'z1', 'zone_name' => 'stores.example.com'], ['api_token']);

    Http::fake([
        'api.cloudflare.com/client/v4/user/tokens/verify' => Http::sequence()
            ->push(['success' => true, 'result' => ['status' => 'active']])
            ->push(['success' => false, 'errors' => [['message' => 'Invalid API Token']]], 401),
        'api.cloudflare.com/client/v4/zones/z1' => Http::response(['success' => true, 'result' => ['name' => 'example.com']]),
    ]);

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/cloudflare/test'))
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('Authorization', 'Bearer tok-123456789012'));

    $response = $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/integrations/cloudflare/test'))
        ->assertUnprocessable()
        ->assertJsonPath('data.error', 'auth_failed')
        ->assertJsonPath('data.status', 'failed');

    expect($response->getContent())->not->toContain('tok-123456789012');

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/integrations/cloudflare'))
        ->assertSee('Invalid API Token');
});

it('tests Google sign-in credentials with an invalid-grant probe', function (): void {
    $admin = integrationsAdmin('integrations.manage');
    app(SettingsRepositoryContract::class)->put('google', ['enabled' => true, 'client_id' => 'cid.apps.googleusercontent.com', 'client_secret' => 'GOCSPX-secret-r8Td'], ['client_secret']);

    Http::fake(['oauth2.googleapis.com/token' => Http::sequence()
        ->push(['error' => 'invalid_grant'], 400)
        ->push(['error' => 'invalid_client', 'error_description' => 'Unauthorized'], 401)]);
    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/google/test'))->assertOk();

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/google/test'))
        ->assertUnprocessable()
        ->assertJsonPath('data.error', 'auth_failed');
});

it('uploads the Apple .p8 key and signs an ES256 client secret for the test', function (): void {
    $admin = integrationsAdmin('integrations.manage');
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);

    $this->actingAs($admin, 'admin')
        ->put($this->centralUrl('/admin/integrations/apple'), [
            'enabled' => '1', 'client_id' => 'com.nogrgr.signin', 'team_id' => '8H2K9LQ3XZ', 'key_id' => 'Q7MZ43PL2N',
            'private_key' => UploadedFile::fake()->createWithContent('AuthKey_Q7MZ43PL2N.p8', $pem),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    expect(app(SettingsRepositoryContract::class)->get('apple', 'private_key'))->toBe(trim($pem));

    Http::fake(['appleid.apple.com/auth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/apple/test'))->assertOk();

    Http::assertSent(function (HttpRequest $request): bool {
        [$header, $payload, $signature] = explode('.', (string) $request['client_secret']);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        return json_decode(base64_decode(strtr($header, '-_', '+/')), true)['alg'] === 'ES256'
            && $claims['iss'] === '8H2K9LQ3XZ' && $claims['sub'] === 'com.nogrgr.signin'
            && strlen(base64_decode(strtr($signature, '-_', '+/'))) === 64;
    });

    $this->actingAs($admin, 'admin')
        ->put($this->centralUrl('/admin/integrations/apple'), [
            'enabled' => '1', 'client_id' => 'x', 'team_id' => 'x', 'key_id' => 'x',
            'private_key' => UploadedFile::fake()->createWithContent('bad.p8', 'not a key'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('private_key');
});

it('tests the Reverb app with a signed Pusher-protocol request', function (): void {
    $admin = integrationsAdmin('integrations.manage');
    app(SettingsRepositoryContract::class)->put('broadcasting', ['enabled' => true, 'driver' => 'reverb', 'app_id' => '1001', 'key' => 'nogrgr-prod', 'secret' => 'reverb-secret-7Hd2', 'host' => 'ws.nogrgr.test', 'port' => 443, 'scheme' => 'https'], ['secret']);

    Http::fake(['ws.nogrgr.test/*' => Http::response(['channels' => []])]);

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/broadcasting/test'))->assertOk();

    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'ws.nogrgr.test') && str_contains($request->url(), '/apps/1001/channels?')
        && str_contains($request->url(), 'auth_signature=') && ! str_contains($request->url(), 'reverb-secret'));

    app(IntegrationConfigOverlay::class)->apply();
    expect(config('broadcasting.default'))->toBe('reverb')
        ->and(config('broadcasting.connections.reverb.key'))->toBe('nogrgr-prod')
        ->and(config('reverb.apps.apps.0.secret'))->toBe('reverb-secret-7Hd2');
});

it('saves the AI translation routing and tests each provider', function (): void {
    $admin = integrationsAdmin('integrations.manage');

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/ai'), [
            'provider' => 'freellmapi', 'custom_provider' => 'freellmapi', 'paid_provider' => 'openai',
            'openai_base_url' => 'https://api.openai.com/v1', 'openai_api_key' => '', 'openai_model' => 'gpt-4.1-mini',
            'freellmapi_base_url' => 'http://llm.local:3001/v1', 'freellmapi_api_key' => '', 'freellmapi_model' => 'auto',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('openai_api_key');

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/integrations/ai'), [
            'provider' => 'freellmapi', 'custom_provider' => 'freellmapi', 'paid_provider' => 'openai',
            'openai_base_url' => 'https://api.openai.com/v1', 'openai_api_key' => 'sk-proj-abcdefghijklYt4q', 'openai_model' => 'gpt-4.1-mini',
            'freellmapi_base_url' => 'http://llm.local:3001/v1', 'freellmapi_api_key' => '', 'freellmapi_model' => 'auto',
        ])
        ->assertOk()
        ->assertJsonMissing(['sk-proj-abcdefghijklYt4q']);

    expect(config('ai.translation.paid_provider'))->toBe('openai')
        ->and(config('ai.providers.freellmapi.base_url'))->toBe('http://llm.local:3001/v1')
        ->and(config('openai.api_key'))->toBe('sk-proj-abcdefghijklYt4q');

    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4.1-mini'], ['id' => 'gpt-4o-mini']]]),
        'llm.local:3001/v1/models' => Http::response(['error' => ['message' => 'down']], 503),
    ]);

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/ai/test'), ['target' => 'openai'])
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/ai/test'), ['target' => 'freellmapi'])
        ->assertUnprocessable()
        ->assertJsonPath('data.error', 'unreachable');

    $this->actingAs($admin, 'admin')->postJson($this->centralUrl('/admin/integrations/ai/test'), ['target' => 'other'])
        ->assertJsonValidationErrors('target');
});
