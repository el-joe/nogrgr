{{--
    One purpose of a gateway (vendor payments / tenant registration): enabled switch,
    test/live mode, credentials (secrets masked, blank = keep), currencies, test action.
    @param \App\Modules\Integrations\Models\PaymentGateway $gateway
    @param \App\Modules\Integrations\Enums\GatewayPurpose $purpose
    @param \App\Modules\Integrations\Models\PaymentGatewayConfig $config
    @param array $credentials
    @param array $currencyOptions
    @param bool $canManage
--}}
@php
    use App\Modules\Integrations\Enums\GatewayMode;

    $prefix = 'configs['.$purpose->value.']';
    $statusKey = $purpose->value;
    $last = $config->last_tested_at === null ? null : new \App\Modules\Integrations\DTOs\ConnectionTestResult(
        $config->last_error === null,
        $config->last_error === null ? null : \App\Modules\Integrations\Enums\ConnectionErrorType::tryFrom(\Illuminate\Support\Str::before($config->last_error, ':')),
        $config->last_error !== null && str_contains($config->last_error, ': ') ? \Illuminate\Support\Str::after($config->last_error, ': ') : null,
        $config->last_tested_at->toIso8601String(),
    );
@endphp
<x-ui::card>
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-control bg-primary-subtle text-ink-brand"><x-ui::icon :name="$purpose->icon()" :size="20" /></span>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-ink">{{ $purpose->label() }}</h3>
                <p class="text-[13px] text-ink-secondary">{{ __('integrations::admin.gateways.purposes.'.$purpose->value) }}</p>
            </div>
        </div>
        <x-ui::switch :name="$prefix.'[enabled]'" :label="__('integrations::admin.gateways.enabled')" :checked="$config->is_enabled" :disabled="! $canManage" />
    </div>

    <fieldset class="mb-5 grid grid-cols-2 gap-1 rounded-control bg-subtle p-1" @disabled(! $canManage)>
        <legend class="sr-only">{{ __('integrations::admin.gateways.fields.mode') }}</legend>
        @foreach (GatewayMode::cases() as $mode)
            <label class="cursor-pointer">
                <input type="radio" class="peer sr-only" name="{{ $prefix }}[mode]" value="{{ $mode->value }}" @checked(($config->mode ?? GatewayMode::Test) === $mode)>
                <span @class([
                    'flex h-9 items-center justify-center rounded-chip text-[13px] text-ink-secondary transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-primary',
                    'peer-checked:bg-surface peer-checked:font-medium peer-checked:text-ink peer-checked:shadow-card' => $mode === GatewayMode::Test,
                    'peer-checked:bg-primary peer-checked:font-medium peer-checked:text-on-primary' => $mode === GatewayMode::Live,
                ])>{{ __('integrations::admin.gateways.modes.'.$mode->value) }}</span>
            </label>
        @endforeach
    </fieldset>

    <div class="grid gap-5">
        @forelse ($gateway->code->credentialFields() as $field)
            @php $state = $credentials[$field->key]; $label = __('integrations::admin.gateways.credentials.'.$field->key); @endphp
            @if ($field->isSecret())
                <x-ui::input
                    :name="$prefix.'[credentials]['.$field->key.']'"
                    type="password"
                    icon="key"
                    :label="$label"
                    :required="$field->required && ! $state['stored']"
                    :placeholder="$state['masked'] ?? ''"
                    :helper="__('integrations::admin.gateways.secret_helper')"
                    autocomplete="new-password"
                    maxlength="{{ $field->maxLength }}"
                    :disabled="! $canManage"
                />
            @else
                <x-ui::input :name="$prefix.'[credentials]['.$field->key.']'" :label="$label" :value="$state['value']" :required="$field->required" dir="ltr" maxlength="{{ $field->maxLength }}" :disabled="! $canManage" />
            @endif
        @empty
            <p class="rounded-control bg-subtle px-4 py-3 text-[13px] text-ink-secondary">{{ __('integrations::admin.gateways.no_credentials') }}</p>
        @endforelse

        <x-ui::select
            :name="$prefix.'[currencies]'"
            :label="__('integrations::admin.gateways.fields.currencies')"
            :options="$currencyOptions"
            :selected="$config->currencies ?? []"
            multiple
            :helper="__('integrations::admin.gateways.currencies_helper')"
            :placeholder="__('integrations::admin.gateways.currencies_placeholder')"
            :disabled="! $canManage"
        />
    </div>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
        <div class="grid gap-1">
            @include('integrations::admin.integrations._status', ['key' => $statusKey, 'status' => $config->status()])
            @include('integrations::admin.integrations._last-test', ['key' => $statusKey, 'last' => $last])
        </div>
        @if ($canManage && ! $gateway->code->isOffline())
            <x-ui::button variant="secondary" size="sm" icon="zap" data-integration-test="{{ route('admin.integrations.gateways.test', [$gateway->code, $purpose]) }}" data-test-target="{{ $statusKey }}" data-test-dirty-check="#gateway-form">{{ __('integrations::admin.test.button') }}</x-ui::button>
        @endif
    </div>
</x-ui::card>
