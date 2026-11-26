{{--
    Admin › Integrations › Payment gateways (ADM-100a, Figma Admin Panel 51:17971):
    master list + overview table (purposes, mode, fee, active switch).
--}}
@php
    use App\Modules\Integrations\Enums\GatewayPurpose;

    $canManage = (bool) auth('admin')->user()?->can('integrations.manage');
@endphp
<x-layouts.panel surface="admin" :title="__('integrations::admin.tabs.gateways').' · '.__('integrations::admin.title')" data-page="admin-integrations">
    @include('integrations::admin.integrations._header', ['active' => 'gateways'])

    <div class="grid items-start gap-6 *:min-w-0 lg:grid-cols-[280px_minmax(0,1fr)]">
        @include('integrations::admin.gateways._list', ['gateways' => $gateways, 'current' => null])

        <div class="grid gap-6">
            <x-ui::card :padding="false">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">{{ __('integrations::admin.gateways.overview') }}</h2>
                    <p class="text-[13px] text-ink-secondary">{{ __('integrations::admin.gateways.overview_help') }}</p>
                </div>
                @if ($gateways->isEmpty())
                    <x-ui::empty-state :title="__('integrations::admin.gateways.empty_title')" :description="__('integrations::admin.gateways.empty_description')" />
                @else
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="bg-subtle text-start text-xs font-medium text-ink-secondary">
                                <tr>
                                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('integrations::admin.gateways.columns.gateway') }}</th>
                                    @foreach (GatewayPurpose::cases() as $purpose)
                                        <th scope="col" class="px-5 py-3 text-start font-medium">{{ $purpose->label() }}</th>
                                    @endforeach
                                    <th scope="col" class="px-5 py-3 text-end font-medium">{{ __('integrations::admin.gateways.columns.fee') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('integrations::admin.gateways.columns.active') }}</th>
                                    <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('integrations::admin.gateways.columns.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($gateways as $gateway)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <span class="font-medium text-ink">{{ $gateway->code->brand() }}</span>
                                            <span class="block text-xs text-ink-secondary" dir="ltr">{{ $gateway->code->methods() }}</span>
                                        </td>
                                        @foreach (GatewayPurpose::cases() as $purpose)
                                            @php $config = $gateway->config($purpose); @endphp
                                            <td class="px-5 py-3">
                                                @if ($config?->is_enabled)
                                                    <x-ui::badge :tone="$config->mode === \App\Modules\Integrations\Enums\GatewayMode::Live ? 'success' : 'warning'">{{ $config->mode->label() }}</x-ui::badge>
                                                @else
                                                    <span class="text-ink-muted">{{ __('integrations::admin.gateways.disabled') }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-5 py-3 text-end tabular-nums" dir="ltr">{{ number_format($gateway->fee_percent_bps / 100, 2) }}% + {{ $gateway->feeFixed()->format() }}</td>
                                        <td class="px-5 py-3">
                                            @if ($canManage)
                                                <x-ui::switch :name="'is_active_'.$gateway->code->value" field="is_active" :url="route('admin.integrations.gateways.toggle', $gateway->code)" :checked="$gateway->is_active" :aria-label="__('integrations::admin.gateways.toggle', ['name' => $gateway->code->brand()])" :disabled="! $gateway->is_active && ! $gateway->canBeActivated()" :title="! $gateway->is_active && ! $gateway->canBeActivated() ? __('integrations::admin.gateways.activate_needs_config', ['name' => $gateway->code->brand()]) : null" />
                                            @else
                                                <x-ui::status :status="$gateway->is_active ? 'active' : 'inactive'" />
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-end">
                                            <x-ui::icon-button icon="edit" size="sm" :label="__('integrations::admin.gateways.edit', ['name' => $gateway->code->brand()])" :href="route('admin.integrations.gateways.edit', $gateway->code)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui::card>

            <div class="flex items-start gap-2.5 rounded-control bg-info-bg px-4 py-3 text-[13px] text-ink" role="note">
                <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
                <p>{{ __('integrations::admin.gateways.vendor_note') }}</p>
            </div>
        </div>
    </div>
</x-layouts.panel>
