{{--
    Admin › Localization › Language purchase detail (ADM-094 "details"): store, item,
    amount and dates, the invoice and every payment attempt (gateway, reference,
    status). Read-only: Billing owns purchases, invoices and payment logs.
--}}
@php
    /** @var \App\Modules\Billing\Models\AddonPurchase $purchase */
    $admin = auth('admin')->user();
    $dateTime = fn ($value) => $value?->translatedFormat('j M Y H:i') ?? '—';
    $invoice = $purchase->invoices->first();
@endphp
<x-layouts.panel surface="admin" :title="__('localization::purchases.detail_title', ['id' => $purchase->id])">
    <x-ui::page-header
        :title="__('localization::purchases.detail_title', ['id' => $purchase->id])"
        :subtitle="$description"
        :breadcrumbs="[__('navigation.admin.localization') => null, __('localization::purchases.tab') => route('admin.localization.purchases.index'), '#'.$purchase->id => null]"
    >
        <x-slot:status>
            @include('localization::admin.purchases._status', ['status' => $purchase->status])
        </x-slot:status>
    </x-ui::page-header>

    <div class="grid items-start gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
        <x-ui::card :title="__('localization::purchases.details')">
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.store') }}</dt>
                <dd class="text-end text-ink">
                    @if ($purchase->tenant && $admin?->can('vendors.view'))
                        <a href="{{ route('admin.vendors.show', $purchase->tenant_id) }}" class="font-medium text-ink-brand hover:underline">{{ $purchase->tenant->getAttribute('name') }}</a>
                    @else
                        {{ $purchase->tenant?->getAttribute('name') ?? '—' }}
                    @endif
                </dd>
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.type') }}</dt>
                <dd class="text-end text-ink">{{ $purchase->type->label() }}</dd>
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.purchase') }}</dt>
                <dd class="text-end text-ink">{{ $description }} <span class="text-ink-muted" dir="ltr">({{ $purchase->item_code }})</span></dd>
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.amount') }}</dt>
                <dd class="text-end font-medium text-ink"><bdi dir="ltr">{{ $purchase->amount()->format() }}</bdi></dd>
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.created') }}</dt>
                <dd class="text-end text-ink">{{ $dateTime($purchase->created_at) }}</dd>
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.paid') }}</dt>
                <dd class="text-end text-ink">{{ $dateTime($purchase->paid_at) }}</dd>
                <dt class="text-ink-secondary">{{ __('localization::purchases.fields.invoice') }}</dt>
                <dd class="text-end text-ink">
                    @if ($invoice && $admin?->can('finance.invoices.view'))
                        <a href="{{ route('admin.finance.invoices.show', $invoice->id) }}" class="font-medium text-ink-brand hover:underline" dir="ltr">{{ $invoice->number }}</a>
                    @else
                        <span dir="ltr">{{ $invoice?->number ?? '—' }}</span>
                    @endif
                </dd>
            </dl>
        </x-ui::card>

        <x-ui::card :title="__('localization::purchases.payments')">
            @if ($logs->isEmpty())
                <x-ui::empty-state compact icon="file" :title="__('localization::purchases.no_payments')" :description="__('localization::purchases.no_payments_description')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-start text-xs text-ink-secondary">
                                <th scope="col" class="py-2 text-start font-medium">{{ __('localization::purchases.fields.reference') }}</th>
                                <th scope="col" class="py-2 text-start font-medium">{{ __('localization::purchases.columns.gateway') }}</th>
                                <th scope="col" class="py-2 text-end font-medium">{{ __('localization::purchases.columns.amount') }}</th>
                                <th scope="col" class="py-2 text-start font-medium">{{ __('localization::purchases.columns.date') }}</th>
                                <th scope="col" class="py-2 text-start font-medium">{{ __('localization::purchases.columns.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr class="border-b border-line last:border-0">
                                    <td class="py-3" dir="ltr">
                                        @if ($admin?->can('finance.payment-logs.view'))
                                            <a href="{{ route('admin.finance.payment-logs.show', $log->id) }}" class="font-medium text-ink-brand hover:underline">{{ $log->gateway_reference ?? '#'.$log->id }}</a>
                                        @else
                                            {{ $log->gateway_reference ?? '#'.$log->id }}
                                        @endif
                                    </td>
                                    <td class="py-3 text-ink">{{ $log->gateway->label() }}</td>
                                    <td class="py-3 text-end"><bdi dir="ltr">{{ $log->amount()->format() }}</bdi></td>
                                    <td class="py-3 text-ink-secondary">{{ $dateTime($log->created_at) }}</td>
                                    <td class="py-3">@include('billing::admin._status', ['status' => $log->status])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui::card>
    </div>
</x-layouts.panel>
