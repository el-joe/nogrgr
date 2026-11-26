{{--
    Admin › Integrations › Payment gateway edit (ADM-100b, Figma Admin Panel 51:17971):
    gateway header (active switch), Credentials tab with the vendor-payments and
    tenant-registration configs, Fees tab (basis points + fixed USD).
--}}
@php
    use App\Modules\Integrations\Enums\GatewayPurpose;

    /** @var \App\Modules\Integrations\Models\PaymentGateway $current */
    $canManage = (bool) auth('admin')->user()?->can('integrations.manage');
    $name = $current->code->brand();
@endphp
<x-layouts.panel surface="admin" :title="$name.' · '.__('integrations::admin.title')" data-page="admin-integrations">
    @include('integrations::admin.integrations._header', ['active' => 'gateways'])

    <div class="grid items-start gap-6 *:min-w-0 lg:grid-cols-[280px_minmax(0,1fr)]">
        @include('integrations::admin.gateways._list', ['gateways' => $gateways, 'current' => $current])

        <form id="gateway-form" method="POST" action="{{ route('admin.integrations.gateways.update', $current->code) }}" data-ajax data-unsaved-form novalidate class="grid min-w-0 gap-6">
            @csrf
            @method('PUT')

            <x-ui::card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="ui-gateway-tile grid size-11 shrink-0 place-items-center rounded-control text-sm font-semibold" data-gateway="{{ $current->code->value }}" aria-hidden="true">{{ $current->code->initials() }}</span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-ink">{{ $name }}</h2>
                                @include('integrations::admin.gateways._badge', ['gateway' => $current])
                            </div>
                            <p class="text-[13px] text-ink-secondary"><bdi dir="ltr">{{ $current->code->methods() }}</bdi> · {{ __('integrations::admin.gateways.driver', ['code' => $current->code->value]) }}</p>
                        </div>
                    </div>
                    @if ($canManage)
                        <div data-unsaved-ignore>
                            <x-ui::switch name="gateway_active" field="is_active" :url="route('admin.integrations.gateways.toggle', $current->code)" :checked="$current->is_active" :label="__('integrations::admin.gateways.columns.active')" :unchecked-value="null" :disabled="! $current->is_active && ! $current->canBeActivated()" :description="! $current->is_active && ! $current->canBeActivated() ? __('integrations::admin.gateways.activate_after_save') : null" />
                        </div>
                    @endif
                </div>
            </x-ui::card>

            <x-ui::tabs id="gateway-tabs" variant="segmented" :label="$name" :items="[
                'credentials' => __('integrations::admin.gateways.tabs.credentials'),
                'fees' => __('integrations::admin.gateways.tabs.fees'),
            ]">
                <x-ui::tab-panel tabs="gateway-tabs" key="credentials" :active="request('tab', 'credentials') !== 'fees'" class="mt-4">
                    <div class="grid items-start gap-6 xl:grid-cols-2">
                        @foreach (GatewayPurpose::cases() as $purpose)
                            @include('integrations::admin.gateways._config-card', [
                                'gateway' => $current,
                                'purpose' => $purpose,
                                'config' => $configs[$purpose->value]['config'],
                                'credentials' => $configs[$purpose->value]['credentials'],
                                'canManage' => $canManage,
                            ])
                        @endforeach
                    </div>
                </x-ui::tab-panel>
                <x-ui::tab-panel tabs="gateway-tabs" key="fees" :active="request('tab') === 'fees'" class="mt-4">
                    <x-ui::card class="max-w-2xl">
                        <h3 class="text-base font-semibold text-ink">{{ __('integrations::admin.gateways.fees_title') }}</h3>
                        <p class="mb-5 text-[13px] text-ink-secondary">{{ __('integrations::admin.gateways.fees_help') }}</p>
                        <div class="grid gap-5 md:grid-cols-2">
                            <x-ui::input name="fee_percent" type="number" step="0.01" min="0" max="100" :label="__('integrations::admin.gateways.fields.fee_percent')" :value="number_format($current->fee_percent_bps / 100, 2, '.', '')" required dir="ltr" :disabled="! $canManage" />
                            <x-ui::input name="fee_fixed" type="number" step="0.01" min="0" max="10000" :label="__('integrations::admin.gateways.fields.fee_fixed')" :value="$current->feeFixed()->toDecimal()" required dir="ltr" :disabled="! $canManage" />
                        </div>
                        <p class="mt-4 text-xs text-ink-secondary" dir="auto">{{ __('integrations::admin.gateways.fee_example', ['fee' => $current->feeFor(\App\Shared\Money\Money::of(100))->format()]) }}</p>
                    </x-ui::card>
                </x-ui::tab-panel>
            </x-ui::tabs>

            <div class="flex items-start gap-2.5 rounded-control bg-info-bg px-4 py-3 text-[13px] text-ink" role="note">
                <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
                <p>{{ __('integrations::admin.gateways.vendor_note') }}</p>
            </div>

            @if ($canManage)
                <div class="flex flex-wrap justify-end gap-3">
                    <x-ui::button variant="secondary" :href="route('admin.integrations.gateways.index')">{{ __('ui.cancel') }}</x-ui::button>
                    <x-ui::button type="submit" icon="check">{{ __('integrations::admin.gateways.save', ['name' => $name]) }}</x-ui::button>
                </div>
            @endif
        </form>
    </div>
</x-layouts.panel>
