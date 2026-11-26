{{--
    Vendor › Settings › Languages (VEN-073 / 073a / 073b, Vendor Panel (new screens)
    90:113194). Tabs: Languages | Purchases history.
    Languages: "Your languages" (drag to sort, default, on / off — ar + en free and always
    on) with the paid AI translation status of each bought language (queued, translating %,
    done, failed + retry); "Available to buy" (one USD price per language, awaiting payment);
    the checkout card (shared invoice checkout: hosted page or the inline Stripe card).
--}}
@use('App\Modules\Localization\Enums\TranslationRunStatus')
@php
    $tabItems = [
        'languages' => ['label' => __('localization::vendor_languages.tabs.languages'), 'href' => vendor_route('settings.languages.index')],
        'history' => ['label' => __('localization::vendor_languages.tabs.history'), 'href' => vendor_route('settings.languages.index', ['tab' => 'history'])],
    ];
    $nameOf = fn ($row) => $row['language']?->name ?: strtoupper($row['code']);
@endphp
<x-vendor::layout :title="__('localization::vendor_languages.title')" page="vendor-languages">
    <script type="application/json" data-translation-live>@json($live)</script>
    <div class="grid gap-6">
        <x-ui::page-header :title="__('localization::vendor_languages.title')" :subtitle="__('localization::vendor_languages.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('localization::vendor_languages.title') => null]">
            <x-slot:tabs>
                <x-ui::tabs id="vendor-language-tabs" :active="$tab" :label="__('localization::vendor_languages.title')" :items="$tabItems" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($tab === 'history')
            <x-ui::datatable
                id="vendor-language-purchases"
                :url="vendor_route('settings.languages.history')"
                :columns="[
                    ['data' => 'purchase', 'title' => __('localization::vendor_languages.history.language'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'amount_minor', 'title' => __('localization::vendor_languages.history.amount'), 'align' => 'end', 'searchable' => false],
                    ['data' => 'status', 'title' => __('localization::vendor_languages.history.status'), 'searchable' => false],
                    ['data' => 'created_at', 'title' => __('localization::vendor_languages.history.date'), 'searchable' => false],
                    ['data' => 'invoice_number', 'title' => __('localization::vendor_languages.history.invoice'), 'orderable' => false, 'searchable' => false],
                ]"
                :order="[[3, 'desc']]"
                :empty-title="__('localization::vendor_languages.history.empty_title')"
                :empty-description="__('localization::vendor_languages.history.empty_description')"
            />
        @else
            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
                <div class="grid gap-6">
                    <x-ui::card :title="__('localization::vendor_languages.yours')" :subtitle="$canManage ? __('localization::vendor_languages.yours_hint') : null">
                        <form id="vendor-languages-form" method="POST" action="{{ vendor_route('settings.languages.update') }}" data-ajax data-unsaved-form novalidate>
                            @csrf
                            @method('PUT')
                            <ol class="grid gap-2" role="list" data-language-list>
                                @foreach ($store as $index => $row)
                                    @php
                                        $run = $row['run'];
                                        $tenantRow = $row['row'];
                                        $active = $tenantRow !== null;
                                    @endphp
                                    <li @class(['grid gap-2 rounded-control bg-surface px-3 py-2.5 transition-colors hover:bg-subtle', 'cursor-grab' => $active && $canManage]) data-language-row="{{ $row['code'] }}" @if ($active && $canManage) draggable="true" @endif>
                                        <div class="flex flex-wrap items-center gap-3">
                                            @if ($active)
                                                <input type="hidden" name="order[]" value="{{ $row['code'] }}">
                                                @if ($canManage)
                                                    <button type="button" class="grid size-7 shrink-0 place-items-center rounded-control text-ink-muted hover:bg-subtle focus-visible:outline-2" data-language-handle aria-label="{{ __('localization::vendor_languages.move', ['language' => $nameOf($row)]) }}">
                                                        <x-ui::icon name="menu" :size="16" />
                                                    </button>
                                                @endif
                                                <span class="w-4 text-xs tabular-nums text-ink-muted" data-language-position>{{ $loop->iteration }}</span>
                                            @endif
                                            <span class="grid min-w-32 flex-1">
                                                <span class="truncate text-sm font-medium text-ink">{{ $nameOf($row) }}@if ($row['language']?->native_name && $row['language']->native_name !== $nameOf($row)) <span class="text-ink-secondary">· {{ $row['language']->native_name }}</span>@endif</span>
                                                <span class="text-xs text-ink-secondary">
                                                    {{ $row['core'] ? __('localization::vendor_languages.free') : __('localization::vendor_languages.paid') }}
                                                    @if ($tenantRow?->is_default) · {{ __('localization::vendor_languages.default') }} @endif
                                                </span>
                                            </span>
                                            @if ($tenantRow?->is_default)
                                                <x-ui::badge tone="brand" dot>{{ __('localization::vendor_languages.default') }}</x-ui::badge>
                                            @elseif ($active && $tenantRow->is_enabled)
                                                <x-ui::badge tone="success" dot>{{ __('localization::vendor_languages.active') }}</x-ui::badge>
                                            @endif
                                            @if ($active)
                                                <x-ui::radio name="default" :value="$row['code']" :checked="$tenantRow->is_default" :disabled="! $canManage" :label="__('localization::vendor_languages.make_default')" />
                                                <x-ui::switch :id="'language-enabled-'.$row['code']" name="enabled[]" :value="$row['code']" :checked="$tenantRow->is_enabled || $row['core']" :unchecked-value="null" :disabled="! $canManage || $row['core']" :aria-label="__('localization::vendor_languages.enabled_label', ['language' => $nameOf($row)])" />
                                                @if ($row['core'])
                                                    <input type="hidden" name="enabled[]" value="{{ $row['code'] }}">
                                                @endif
                                            @elseif ($run)
                                                <span data-run-badge="{{ $row['code'] }}">@include('localization::vendor.languages._run-badge', ['run' => $run])</span>
                                            @endif
                                        </div>
                                        @if ($run && ! $active)
                                            <div data-run-slot="{{ $row['code'] }}" data-run-active="{{ $run->status->isActive() ? '1' : '0' }}" aria-live="polite">
                                                @include('localization::vendor.languages._run', ['run' => $run, 'name' => $nameOf($row)])
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                            @if ($canManage)
                                <div class="mt-4 flex justify-end">
                                    <x-ui::button type="submit" icon="check">{{ __('localization::vendor_languages.save') }}</x-ui::button>
                                </div>
                            @endif
                        </form>
                    </x-ui::card>

                    <x-ui::card :title="__('localization::vendor_languages.available')" :padding="false">
                        @if ($purchasable->isEmpty())
                            <div class="px-5 pb-5">
                                <x-ui::empty-state compact icon="languages" :title="__('localization::vendor_languages.none_title')" :description="__('localization::vendor_languages.none')" />
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table data-lead class="ui-table vnd-table w-full min-w-[420px] text-sm">
                                    <thead>
                                        <tr class="border-y border-line bg-subtle text-xs font-medium text-ink-secondary">
                                            <th scope="col" class="px-5 py-3 text-start">{{ __('localization::vendor_languages.columns.language') }}</th>
                                            <th scope="col" class="px-5 py-3 text-end">{{ __('localization::vendor_languages.columns.price') }}</th>
                                            <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('localization::vendor_languages.columns.actions') }}</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($purchasable as $item)
                                            @php $language = $item['language']; @endphp
                                            <tr @class(['border-b border-line last:border-b-0', 'bg-primary-subtle/40' => $buying && $buying['language']->id === $language->id]) data-purchasable="{{ $language->code }}">
                                                <td class="px-5 py-3">
                                                    <span class="grid">
                                                        <span class="font-medium text-ink">{{ $language->native_name ?: $language->name }}</span>
                                                        <span class="text-xs text-ink-secondary"><bdi dir="ltr">{{ $language->code }}</bdi>@if ($language->direction->value === 'rtl') · RTL @endif · {{ $language->name }}</span>
                                                    </span>
                                                </td>
                                                <td class="px-5 py-3 text-end">
                                                    @if ($item['purchase'])
                                                        <x-ui::badge tone="warning" dot>{{ __('localization::vendor_languages.pending_payment') }}</x-ui::badge>
                                                    @endif
                                                    <bdi dir="ltr" class="ms-2 font-semibold text-ink">{{ $language->price()->format() }}</bdi>
                                                </td>
                                                <td class="px-5 py-3 text-end">
                                                    @if ($canManage)
                                                        <x-ui::button size="sm" variant="secondary" icon="credit-card" :href="vendor_route('settings.languages.index', ['buy' => $language->id])">{{ $item['purchase'] ? __('localization::vendor_languages.pay') : __('localization::vendor_languages.buy') }}</x-ui::button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-ui::card>
                </div>

                <x-ui::card :title="__('localization::vendor_languages.checkout.title')" data-language-checkout>
                    @if (session('success') || session('info') || session('error'))
                        @php $flashTone = session('error') ? 'danger' : (session('info') ? 'info' : 'success'); @endphp
                        <p @class(['mb-4 flex items-start gap-2 rounded-control px-3 py-2.5 text-[13px]', 'bg-success-bg text-success' => $flashTone === 'success', 'bg-info-bg text-info' => $flashTone === 'info', 'bg-danger-bg text-danger' => $flashTone === 'danger']) role="status">
                            <x-ui::icon :name="$flashTone === 'danger' ? 'x-circle' : 'check-circle'" :size="16" class="mt-0.5 shrink-0" />{{ session('error') ?? session('info') ?? session('success') }}
                        </p>
                    @endif
                    @if ($buying === null)
                        <x-ui::empty-state compact icon="credit-card" :description="__('localization::vendor_languages.checkout.none')" />
                    @else
                        @php $language = $buying['language']; @endphp
                        <dl class="grid gap-2 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-secondary">{{ $language->name }}</dt>
                                <dd class="font-medium text-ink"><bdi dir="ltr">{{ $language->price()->format() }}</bdi></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-line pt-2">
                                <dt class="font-medium text-ink">{{ __('localization::vendor_languages.checkout.total') }}</dt>
                                <dd class="font-semibold text-ink"><bdi dir="ltr">{{ $language->price()->format() }}</bdi></dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs text-ink-secondary">{{ __('localization::vendor_languages.checkout.includes') }}</p>
                        @if (! $canManage)
                            <p class="mt-4 text-xs text-ink-secondary">{{ __('localization::vendor_languages.checkout.no_permission') }}</p>
                        @elseif ($gateways->isEmpty())
                            <p class="mt-4 text-xs text-ink-secondary">{{ __('localization::vendor_languages.checkout.no_gateways') }}</p>
                        @else
                            <form method="POST" action="{{ vendor_route('settings.languages.purchase', ['language' => $language->id]) }}" class="mt-4 grid gap-3" data-language-purchase>
                                @csrf
                                @if ($gateways->count() === 1)
                                    <input type="hidden" name="gateway" value="{{ $gateways->first()->code->value }}">
                                @else
                                    <x-ui::select name="gateway" :label="__('localization::vendor_languages.fields.gateway')" :options="$gateways->mapWithKeys(fn ($gateway) => [$gateway->code->value => $gateway->code->label()])->all()" :selected="$gateways->first()->code->value" :search="false" />
                                @endif
                                <x-ui::stripe-card :gateways="$gateways" :purpose="$purpose" />
                                <x-ui::button type="submit" size="lg" block icon="credit-card">{{ __('localization::vendor_languages.checkout.pay', ['price' => $language->price()->format()]) }}</x-ui::button>
                            </form>
                        @endif
                    @endif
                </x-ui::card>
            </div>
        @endif
    </div>
</x-vendor::layout>
