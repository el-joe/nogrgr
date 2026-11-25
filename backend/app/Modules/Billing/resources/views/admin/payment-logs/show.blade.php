{{--
    Admin › Finance › Payment logs › Detail (ADM-055a, Figma 74:66052 detail panel):
    gateway ref, store, purpose, amounts and dates, raw gateway payload and the status
    change (pending → captured / failed, failed → pending, captured → refunded).
    A refund marks the invoice refunded and requests the affiliate conversion reversal.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('finance.payment-logs.manage');
    $next = collect($log->status->nextStatuses())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    $dateTime = fn ($value) => $value?->translatedFormat('j M Y H:i');
@endphp
<x-layouts.panel surface="admin" :title="__('billing::payments.detail_title', ['reference' => $log->reference()])">
    <x-ui::page-header
        :title="__('billing::payments.detail_title', ['reference' => $log->reference()])"
        :breadcrumbs="[__('navigation.admin.finance') => null, __('billing::payments.title') => route('admin.finance.payment-logs.index'), $log->reference() => null]"
    >
        <x-slot:status>
            @include('billing::admin._status', ['status' => $log->status])
        </x-slot:status>
    </x-ui::page-header>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <x-ui::card :title="__('billing::payments.details')">
            <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm">
                <dt class="text-ink-secondary">{{ __('billing::payments.fields.gateway_ref') }}</dt>
                <dd class="truncate text-end font-medium text-ink" dir="ltr">{{ $log->gateway_reference ?? '—' }}</dd>
                <dt class="text-ink-secondary">{{ __('billing::payments.columns.gateway') }}</dt>
                <dd class="text-end text-ink">{{ $log->gateway->label() }}</dd>
                <dt class="text-ink-secondary">{{ __('billing::payments.fields.store') }}</dt>
                <dd class="text-end text-ink">{{ $log->tenant?->getAttribute('name') ?? '—' }}</dd>
                <dt class="text-ink-secondary">{{ __('billing::payments.columns.purpose') }}</dt>
                <dd class="text-end text-ink">{{ $log->purpose->label() }}</dd>
                <dt class="text-ink-secondary">{{ __('billing::payments.columns.amount') }}</dt>
                <dd class="text-end font-medium text-ink"><bdi dir="ltr">{{ $log->amount()->format() }}</bdi></dd>
                <dt class="text-ink-secondary">{{ __('billing::payments.fields.created') }}</dt>
                <dd class="text-end text-ink">{{ $dateTime($log->created_at) }}</dd>
                @if ($log->paid_at)
                    <dt class="text-ink-secondary">{{ __('billing::payments.fields.captured') }}</dt>
                    <dd class="text-end text-ink">{{ $dateTime($log->paid_at) }}</dd>
                @endif
                @if ($log->refunded_at)
                    <dt class="text-ink-secondary">{{ __('billing::payments.fields.refunded') }}</dt>
                    <dd class="text-end text-ink">{{ $dateTime($log->refunded_at) }}</dd>
                @endif
                <dt class="text-ink-secondary">{{ __('billing::payments.fields.invoice') }}</dt>
                <dd class="text-end text-ink">
                    @if ($log->invoice && auth('admin')->user()?->can('finance.invoices.view'))
                        <a href="{{ route('admin.finance.invoices.show', $log->invoice->id) }}" class="font-medium text-ink-brand hover:underline" dir="ltr">{{ $log->invoice->number }}</a>
                    @else
                        <span dir="ltr">{{ $log->invoice?->number ?? '—' }}</span>
                    @endif
                </dd>
            </dl>

            @if ($log->status === \App\Modules\Billing\Enums\PaymentLogStatus::Refunded)
                <div class="mt-5 flex items-start gap-2 rounded-control bg-warning-bg p-3 text-sm text-ink" role="note">
                    <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
                    <p>{{ __('billing::payments.refund_reversal_note') }}</p>
                </div>
            @endif

            @if ($log->note)
                <div class="mt-5 grid gap-1 text-sm">
                    <p class="font-medium text-ink">{{ __('billing::payments.fields.note') }}</p>
                    <p class="whitespace-pre-line text-ink-secondary">{{ $log->note }}</p>
                </div>
            @endif

            <div class="mt-5 grid gap-2">
                <p class="text-sm font-medium text-ink">{{ __('billing::payments.fields.payload') }}</p>
                @if ($log->payload)
                    <pre class="max-h-80 overflow-auto rounded-control bg-inverse p-4 text-xs leading-5 text-on-inverse" dir="ltr"><code>{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
                @else
                    <x-ui::empty-state compact icon="file" :title="__('billing::payments.no_payload')" />
                @endif
            </div>
        </x-ui::card>

        <x-ui::card :title="__('billing::payments.change_status')">
            @if (! $canManage)
                <p class="text-sm text-ink-secondary">{{ __('billing::payments.no_permission') }}</p>
            @elseif ($next === [])
                <p class="text-sm text-ink-secondary">{{ __('billing::payments.final_status') }}</p>
            @else
                <form method="POST" action="{{ route('admin.finance.payment-logs.status', $log->id) }}" data-ajax class="grid gap-5" novalidate>
                    @csrf
                    @method('PATCH')
                    <x-ui::select name="status" :label="__('billing::payments.fields.status')" :options="$next" :placeholder="__('billing::payments.fields.status_placeholder')" :search="false" required />
                    <x-ui::textarea name="note" :label="__('billing::payments.fields.note')" :rows="3" :maxlength="1000" />
                    @if (array_key_exists(\App\Modules\Billing\Enums\PaymentLogStatus::Refunded->value, $next))
                        <p class="text-xs text-ink-secondary">{{ __('billing::payments.refund_hint') }}</p>
                    @endif
                    <div class="flex justify-end">
                        <x-ui::button type="submit" icon="check">{{ __('billing::payments.save_status') }}</x-ui::button>
                    </div>
                </form>
            @endif
        </x-ui::card>
    </div>
</x-layouts.panel>
