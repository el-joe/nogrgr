{{-- Refund detail (modal): breakdown, gateway, status and its payment log (attempts). --}}
<div class="grid gap-4" data-modal-title="{{ $refund->reference }}" data-modal-subtitle="#{{ $refund->order_number }} · {{ $refund->source->label() }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-2xl font-semibold text-ink" dir="ltr">{{ $refund->money()->format() }}</p>
        @include('commerce::partials.status-badge', ['value' => $refund->status, 'context' => 'refund'])
    </div>
    @if ($refund->failure_reason)
        <p class="rounded-control bg-danger-bg px-3 py-2 text-sm text-danger" role="alert">{{ $refund->failure_reason }}</p>
    @endif
    <dl class="grid gap-2 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.items') }}</dt><dd class="text-ink" dir="ltr">{{ $refund->money('items_amount')->format() }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.shipping') }}</dt><dd class="text-ink" dir="ltr">{{ $refund->money('shipping_amount')->format() }}</dd></div>
        @if ($refund->return_fee > 0)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.fee') }}</dt><dd class="text-ink" dir="ltr">− {{ $refund->money('return_fee')->format() }}</dd></div>@endif
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.method') }}</dt><dd class="text-ink">{{ $refund->method->label() }}{{ $refund->gateway ? ' · '.\App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($refund->gateway) : '' }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.reason') }}</dt><dd class="text-end text-ink">{{ $refund->reason }}</dd></div>
        @if ($refund->returnRequest)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.return') }}</dt><dd class="text-ink" dir="ltr">{{ $refund->returnRequest->reference }}</dd></div>@endif
        @if ($refund->gateway_refund_id)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.gateway_id') }}</dt><dd class="max-w-48 truncate text-ink" dir="ltr">{{ $refund->gateway_refund_id }}</dd></div>@endif
        @if ($refund->processed_at)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.processed') }}</dt><dd class="text-ink">{{ $refund->processed_at->translatedFormat('j M, H:i') }}{{ $refund->processed_by_name ? ' · '.$refund->processed_by_name : '' }}</dd></div>@endif
    </dl>
    @if ($refund->notes)<p class="whitespace-pre-line rounded-control bg-subtle px-3 py-2 text-sm text-ink">{{ $refund->notes }}</p>@endif
    <div>
        <h3 class="mb-2 text-sm font-semibold text-ink">{{ __('after-sales::refunds.payment_log') }}</h3>
        @if ($refund->attemptLog->isEmpty())
            <p class="text-sm text-ink-secondary">{{ __('after-sales::refunds.no_attempts') }}</p>
        @else
            <ul class="grid gap-2">
                @foreach ($refund->attemptLog as $attempt)
                    <li class="flex items-center justify-between gap-3 rounded-control bg-subtle px-3 py-2 text-sm">
                        <span class="min-w-0"><span class="block text-ink">{{ $attempt->created_at?->translatedFormat('j M, H:i') }} · {{ $attempt->actor_type->label() }}</span>@if ($attempt->error)<span class="block truncate text-xs text-danger">{{ $attempt->error }}</span>@endif</span>
                        @include('commerce::partials.status-badge', ['value' => $attempt->status, 'context' => 'refund'])
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @if ($actions !== [])
        @php $vendor = ($surface ?? 'admin') === 'vendor'; @endphp
        <div class="-mx-5 -mb-5 flex flex-wrap justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
            @foreach (array_reverse($actions) as $action)
                <x-ui::button :variant="$action === 'reject' ? 'danger' : ($action === 'retry' ? 'primary' : 'secondary')" data-remote-modal="refund-modal" :data-url="$vendor ? vendor_route('sales.refunds.action.form', ['refund' => $refund->id, 'action' => $action]) : route('admin.refunds.action.form', [$refund->id, $action])">{{ __('after-sales::refunds.actions.'.$action) }}</x-ui::button>
            @endforeach
        </div>
    @endif
</div>
