<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Models\Currency;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Models\StoreSetting;
use Illuminate\Support\Str;

/*
| RQA-VEN-011: Settings › Currencies must save exactly what the browser posts. The array
| switches (default[] / active[]) used to carry a hidden "0" fallback each, so `distinct`
| failed on every save. The form is parsed like a browser would serialise it.
*/

/**
 * @return array<string, mixed> the urlencoded body a browser submits for the form
 */
function currenciesBrowserBody(string $html, array $toggleOff = []): array
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $form = $dom->getElementById('vendor-currencies-form');
    $pairs = [];

    foreach ($form->getElementsByTagName('input') as $input) {
        $name = $input->getAttribute('name');
        if ($name === '' || $input->hasAttribute('disabled')) {
            continue;
        }
        $type = strtolower($input->getAttribute('type'));
        $value = $input->getAttribute('value');
        if ($type === 'checkbox' && (! $input->hasAttribute('checked') || in_array($name.'='.$value, $toggleOff, true))) {
            continue;
        }
        $pairs[] = urlencode($name).'='.urlencode($value);
    }
    parse_str(implode('&', $pairs), $body);

    return $body;
}

beforeEach(function (): void {
    $currency = fn (string $code, string $rate): Currency => tap(Currency::query()->where('code', $code)->first() ?? Currency::factory()->code($code, $rate)->create(), fn (Currency $c) => $c->forceFill(['rate' => $rate, 'is_active' => true, 'is_base' => $code === 'USD'])->save());
    $currency('USD', '1.000000');
    $currency('SAR', '3.750000');
    $currency('AED', '3.672500');
    $sa = Country::query()->where('iso2', 'SA')->first() ?? Country::factory()->iso('SA')->create();
    $ae = Country::query()->where('iso2', 'AE')->first() ?? Country::factory()->iso('AE')->create();
    $sa->forceFill(['currency_code' => 'SAR', 'is_active' => true])->save();
    $ae->forceFill(['currency_code' => 'AED', 'is_active' => true])->save();
    $this->tenant = $this->createTenant('curf-'.Str::lower(Str::random(6)), ['selling_country_ids' => [$sa->id, $ae->id]]);
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = $this->tenantUrl($this->tenant, 'admin/settings/currencies');
});

it('renders no hidden "0" fallback next to the array switches', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get($this->url)->assertOk()
        ->assertDontSee('name="default[]" value="0"', false)
        ->assertDontSee('name="active[]" value="0"', false);
});

it('saves an unchanged form exactly as the browser posts it', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $body = currenciesBrowserBody($this->get($this->url)->getContent());

    expect($body['active'])->toEqualCanonicalizing(['AED', 'SAR'])
        ->and($body['default'])->toEqualCanonicalizing(['AED', 'SAR']);

    $this->put($this->url, $body, ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk();
});

it('saves a toggled-off switch posted by the browser', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $body = currenciesBrowserBody($this->get($this->url)->getContent(), ['active[]=AED', 'default[]=SAR']);

    $this->put($this->url, $body, ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk();

    expect($this->tenant->run(fn () => StoreSetting::query()->where('key', StoreSettingKey::Currencies->value)->first()?->value))
        ->toBe(['inactive' => ['AED'], 'not_default' => ['SAR']]);
});
