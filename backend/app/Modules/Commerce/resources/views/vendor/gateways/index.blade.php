{{--
    Vendor › Settings › Payment gateways (VEN-078 / 078a, Figma Vendor Panel 90:116465):
    the selected gateway's editor (mode, use own merchant account, keys masked, check
    connection, save, set primary), the gateways table (mode, status) and the payment
    readiness checklist. Credentials are stored encrypted in the store database.
--}}
@php
    $staff = auth('vendor')->user();
    $canManage = (bool) $staff?->can('settings.payment-gateways.manage');
    $row = $current?->row;
    $stateTone = ['ready' => 'success', 'testing' => 'warning', 'needs_attention' => 'warning', 'key_rejected' => 'danger', 'hidden' => 'neutral', 'off' => 'neutral'];
@endphp
<x-vendor::layout :title="__('commerce::gateways.title')" page="vendor-orders">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('commerce::gateways.title')" :subtitle="__('commerce::gateways.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('commerce::gateways.title') => null]" />

        @if ($list === [])
            <x-ui::card>
                <x-ui::empty-state icon="credit-card" :title="__('commerce::gateways.none_title')" :description="__('commerce::gateways.none_description')" />
            </x-ui::card>
        @else
            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid content-start gap-6">
                    <x-ui::card>
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="ui-gateway-tile grid size-10 shrink-0 place-items-center rounded-control text-sm font-semibold" data-gateway="{{ $current->code->value }}" aria-hidden="true">{{ $current->code->initials() }}</span>
                                <div>
                                    <h2 class="ui-card-title font-semibold text-ink">{{ $current->code->brand() }}</h2>
                                    <p class="text-xs text-ink-secondary">{{ $current->code->methods() }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($row->is_primary)<x-ui::badge tone="brand">{{ __('commerce::gateways.primary') }}</x-ui::badge>@endif
                                <x-ui::badge :tone="$stateTone[$current->state] ?? 'neutral'">{{ __('commerce::gateways.states.'.$current->state) }}</x-ui::badge>
                            </div>
                        </div>
                        <form id="gateway-form" method="POST" action="{{ vendor_route('settings.payment-gateways.update', ['code' => $current->code->value]) }}" data-ajax class="grid gap-4" x-data="{ own: {{ $row->use_own ? 'true' : 'false' }} }">
                            @csrf
                            @method('PUT')
                            <fieldset class="grid gap-2" @disabled(! $canManage)>
                                <legend class="mb-1 text-sm font-medium text-ink">{{ __('commerce::gateways.fields.mode') }}</legend>
                                <div class="flex gap-6">
                                    @foreach ($modes as $value => $label)
                                        <label class="inline-flex items-center gap-2 text-sm text-ink"><input type="radio" name="mode" value="{{ $value }}" class="ui-radio" @checked($row->mode->value === $value)> {{ $value === 'test' ? __('commerce::gateways.sandbox') : __('commerce::gateways.live') }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div class="flex flex-wrap gap-6">
                                <x-ui::switch name="is_active" :label="__('commerce::gateways.fields.active')" :checked="$row->is_active" :disabled="! $canManage" />
                                <x-ui::switch name="is_hidden" :label="__('commerce::gateways.fields.hidden')" :checked="$row->is_hidden" :disabled="! $canManage" />
                                <x-ui::switch name="use_own" :label="__('commerce::gateways.fields.use_own')" :checked="$row->use_own" :disabled="! $canManage" x-on:change="own = $event.target.checked" />
                            </div>
                            <p class="text-xs text-ink-secondary" x-show="! own">{{ __('commerce::gateways.platform_keys') }}</p>
                            <div class="grid gap-4" x-show="own" @if (! $row->use_own) x-cloak @endif>
                                @foreach ($form as $key => $field)
                                    <x-ui::input
                                        :name="'credentials['.$key.']'"
                                        :label="$field['label']"
                                        :type="$field['secret'] ? 'password' : 'text'"
                                        :value="$field['value']"
                                        :placeholder="$field['secret'] && $field['stored'] ? $field['masked'] : ''"
                                        :helper="$field['secret'] && $field['stored'] ? __('commerce::gateways.secret_kept') : null"
                                        :error="$key === array_key_first(array_filter($form, fn ($f) => $f['secret'])) && $row->use_own && $row->last_error ? __('commerce::gateways.key_rejected') : null"
                                        autocomplete="off" dir="ltr" :disabled="! $canManage"
                                    />
                                @endforeach
                            </div>
                            @if ($row->last_tested_at)
                                <p class="text-xs text-ink-secondary">{{ __('commerce::gateways.last_tested', ['date' => $row->last_tested_at->diffForHumans()]) }}@if ($row->last_error) · <span class="text-danger">{{ $row->last_error }}</span>@endif</p>
                            @endif
                            @if ($canManage)
                                <div class="flex flex-wrap gap-2">
                                    @if ($row->exists && $row->use_own)
                                        <x-ui::button variant="secondary" icon="zap" data-confirm="{{ __('commerce::gateways.test_confirm') }}" data-confirm-tone="primary" data-confirm-method="POST" :data-confirm-url="vendor_route('settings.payment-gateways.test', ['code' => $current->code->value])">{{ __('commerce::gateways.check') }}</x-ui::button>
                                    @endif
                                    <x-ui::button type="submit" icon="check">{{ __('commerce::gateways.save') }}</x-ui::button>
                                    @if ($current->ready && ! $row->is_primary)
                                        <x-ui::button variant="secondary" icon="star" data-confirm="{{ __('commerce::gateways.primary_confirm', ['name' => $current->code->brand()]) }}" data-confirm-tone="primary" data-confirm-method="POST" :data-confirm-url="vendor_route('settings.payment-gateways.primary', ['code' => $current->code->value])">{{ __('commerce::gateways.set_primary') }}</x-ui::button>
                                    @endif
                                </div>
                            @endif
                        </form>
                    </x-ui::card>

                    <x-ui::card :padding="false">
                        <h2 class="ui-card-title px-5 pt-5 font-semibold text-ink">{{ __('commerce::gateways.list') }}</h2>
                        <div class="mt-3 overflow-x-auto">
                            <table data-lead class="vnd-table w-full text-sm">
                                <thead class="bg-subtle text-start text-xs text-ink-secondary">
                                    <tr><th class="px-5 py-2.5 text-start font-medium">{{ __('commerce::gateways.columns.gateway') }}</th><th class="px-5 py-2.5 text-start font-medium">{{ __('commerce::gateways.columns.mode') }}</th><th class="px-5 py-2.5 text-start font-medium">{{ __('commerce::gateways.columns.status') }}</th></tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($list as $item)
                                        <tr @class(['bg-primary-subtle' => $item->code === $current->code])>
                                            <td class="px-5 py-3">
                                                <a href="{{ vendor_route('settings.payment-gateways.show', ['code' => $item->code->value]) }}" class="block font-medium text-ink hover:text-ink-brand">{{ $item->code->brand() }}@if ($item->row->is_primary) <x-ui::badge tone="brand" class="ms-1">{{ __('commerce::gateways.primary') }}</x-ui::badge>@endif</a>
                                                <span class="text-xs text-ink-secondary">{{ $item->row->use_own ? __('commerce::gateways.own_account') : __('commerce::gateways.platform_account') }}</span>
                                            </td>
                                            <td class="px-5 py-3"><x-ui::badge :tone="$item->row->mode->value === 'live' ? 'success' : 'neutral'">{{ $item->row->mode->value === 'live' ? __('commerce::gateways.live') : __('commerce::gateways.sandbox') }}</x-ui::badge></td>
                                            <td class="px-5 py-3"><x-ui::badge :tone="$stateTone[$item->state] ?? 'neutral'">{{ __('commerce::gateways.states.'.$item->state) }}</x-ui::badge></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-ui::card>
                </div>

                <x-ui::card class="content-start">
                    <h2 class="ui-card-title mb-4 font-semibold text-ink">{{ __('commerce::gateways.readiness.title') }}</h2>
                    <ul class="divide-y divide-line">
                        @foreach ($readiness as $check)
                            <li class="flex items-center gap-3 py-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="$check['done'] ? 'check-circle' : 'alert-triangle'" :size="16" /></span>
                                <span class="flex-1 text-sm text-ink">{{ __('commerce::gateways.readiness.'.$check['key']) }}</span>
                                <x-ui::badge :tone="$check['done'] ? 'success' : 'danger'">{{ $check['done'] ? __('commerce::gateways.readiness.done') : __('commerce::gateways.readiness.fix') }}</x-ui::badge>
                            </li>
                        @endforeach
                    </ul>
                </x-ui::card>
            </div>
        @endif
    </div>
</x-vendor::layout>
