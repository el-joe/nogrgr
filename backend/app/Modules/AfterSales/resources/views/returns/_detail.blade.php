{{--
    Return detail shared by ADM-023a (Figma 70:37613) and VEN-022a (84:104582): action
    buttons (one modal each, filtered by AfterSalesAccess), progress steps, item & reason,
    inspection card, evidence / inspection media, timeline, order card, customer
    messages + reply, internal notes.
--}}
@php
    $vendor = $surface === 'vendor';
    // A refund already raised for the return (pending / retryable / done) uses up its value: no second Refund.
    $actions = array_values(array_filter($actions, fn ($a) => $a !== \App\Modules\AfterSales\Enums\ReturnAction::Refund || $maxRefund->isPositive()));
    $user = $vendor ? auth('vendor')->user() : auth('admin')->user();
    $canManage = (bool) $user?->can('returns.manage');
    $formUrl = fn ($action) => $vendor ? vendor_route('sales.returns.actions.form', ['return' => $return->id, 'action' => $action->value]) : route('admin.returns.actions.form', [$return->id, $action->value]);
    $mediaUrl = fn ($media) => $vendor ? vendor_route('sales.returns.media', ['return' => $return->id, 'media' => $media->id]) : route('admin.returns.media', [$return->id, $media->id]);
    $noteUrl = $vendor ? vendor_route('sales.returns.notes.store', ['return' => $return->id]) : route('admin.returns.notes.store', $return->id);
    $orderUrl = $vendor ? vendor_route('sales.orders.show', ['order' => $order->uuid]) : null;
    $primary = collect($actions)->first(fn ($a) => ! in_array($a->value, ['reject', 'request_info', 'close'], true));
    $title = __('after-sales::returns.detail_title', ['reference' => $return->reference]);
    $day = (int) $order->delivered_at?->diffInDays($return->created_at);
@endphp
<div class="grid gap-6">
    <x-ui::page-header :title="$title"
        :breadcrumbs="$vendor ? [__('navigation.vendor.orders') => vendor_route('sales.orders.index'), __('after-sales::returns.title') => vendor_route('sales.returns.index'), $return->reference => null] : [__('navigation.sections.sales') => null, __('after-sales::returns.title') => route('admin.returns.index'), $return->reference => null]">
        <x-slot:actions>
            @include('commerce::partials.status-badge', ['value' => $return->status])
            @if ($canManage)
                @foreach ($actions as $action)
                    @continue($primary === $action)
                    <x-ui::button :variant="$action->value === 'reject' ? 'danger' : 'secondary'" data-remote-modal="return-modal" :data-url="$formUrl($action)">{{ $action->label() }}</x-ui::button>
                @endforeach
                @if ($primary)
                    <x-ui::button icon="check" data-remote-modal="return-modal" :data-url="$formUrl($primary)">{{ $primary->label() }}</x-ui::button>
                @endif
            @endif
        </x-slot:actions>
    </x-ui::page-header>

    @include('commerce::partials.progress', [
        'steps' => [
            [__('after-sales::returns.steps.requested'), $return->created_at?->translatedFormat('j M')],
            [__('after-sales::returns.steps.approved'), $return->approved_at?->translatedFormat('j M')],
            [__('after-sales::returns.steps.received'), $return->received_at?->translatedFormat('j M')],
            [__('after-sales::returns.steps.inspection'), $return->inspected_at?->translatedFormat('j M')],
            [$return->type->value === 'exchange' ? __('after-sales::returns.steps.exchange') : __('after-sales::returns.steps.refund'), ($return->refunded_at ?? $return->exchange_shipped_at)?->translatedFormat('j M')],
            [__('after-sales::returns.steps.closed'), $return->closed_at?->translatedFormat('j M')],
        ],
        'current' => $return->status->step(),
        'complete' => $return->status->value === 'closed',
        'failed' => in_array($return->status->value, ['rejected', 'cancelled'], true),
    ])

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="grid content-start gap-6">
            <x-ui::card>
                <h2 class="mb-4 font-semibold text-ink">{{ __('after-sales::returns.request') }}</h2>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-ink-secondary">{{ __('after-sales::returns.fields.item') }}</dt><dd class="font-medium text-ink">{{ $return->product_name }}{{ $return->variant_label ? ' · '.$return->variant_label : '' }} × {{ $return->quantity }}</dd></div>
                    <div><dt class="text-xs text-ink-secondary">{{ __('after-sales::returns.fields.type') }}</dt><dd class="font-medium text-ink">{{ $return->type->label() }}</dd></div>
                    <div><dt class="text-xs text-ink-secondary">{{ __('after-sales::returns.fields.reason') }}</dt><dd class="font-medium text-ink">{{ $reasonLabel }}@if ($return->seller_fault) <x-ui::badge tone="warning" class="ms-1">{{ __('after-sales::returns.seller_fault') }}</x-ui::badge>@endif</dd></div>
                    <div><dt class="text-xs text-ink-secondary">{{ __('after-sales::returns.value') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $return->money('refund_amount')->format() }}</dd></div>
                </dl>
                <p class="mt-4 whitespace-pre-line rounded-control bg-subtle px-3 py-2 text-sm text-ink">{{ $return->description }}</p>
                <dl class="mt-4 grid gap-1.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.items') }}</dt><dd class="text-ink" dir="ltr">{{ $return->money('items_amount')->format() }}</dd></div>
                    @if ($return->shipping_amount > 0)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.shipping') }}</dt><dd class="text-ink" dir="ltr">{{ $return->money('shipping_amount')->format() }}</dd></div>@endif
                    @if ($return->return_fee > 0)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::refunds.fields.fee') }}</dt><dd class="text-ink" dir="ltr">− {{ $return->money('return_fee')->format() }}</dd></div>@endif
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.max_refundable_now') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $maxRefund->format() }}</dd></div>
                </dl>
            </x-ui::card>

            @if ($return->inspected_at || $return->media->isNotEmpty())
                <x-ui::card>
                    <h2 class="mb-4 font-semibold text-ink">{{ __('after-sales::returns.inspection') }}</h2>
                    @if ($return->inspected_at)
                        <dl class="grid gap-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.fields.result') }}</dt><dd class="font-medium text-ink">{{ $return->inspection_result?->label() }}</dd></div>
                            @if ($return->inspection_condition)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.fields.condition') }}</dt><dd class="font-medium text-ink">{{ $return->inspection_condition }}</dd></div>@endif
                            <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.fields.restock') }}</dt><dd class="font-medium text-ink">{{ $return->restocked ? __('after-sales::returns.restocked_at', ['date' => $return->restocked_at?->translatedFormat('j M')]) : __('after-sales::returns.not_restocked') }}</dd></div>
                        </dl>
                        @if ($return->inspection_notes)<p class="mt-3 whitespace-pre-line text-sm text-ink">{{ $return->inspection_notes }}</p>@endif
                    @endif
                    @if ($return->media->isNotEmpty())
                        <ul class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
                            @foreach ($return->media as $media)
                                <li>
                                    <a href="{{ $mediaUrl($media) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-control border border-line">
                                        @if ($media->isImage())
                                            <img src="{{ $mediaUrl($media) }}" alt="{{ $media->original_name }}" class="aspect-square w-full object-contain bg-media" loading="lazy">
                                        @else
                                            <span class="grid aspect-square place-items-center bg-subtle text-xs text-ink-secondary"><x-ui::icon name="file" :size="20" />{{ $media->kind->label() }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui::card>
            @endif

            <x-ui::card>
                <h2 class="mb-4 font-semibold text-ink">{{ __('after-sales::returns.timeline') }}</h2>
                <ol class="relative grid gap-4 ps-5">
                    <span class="absolute inset-y-1 start-[5px] w-px bg-line" aria-hidden="true"></span>
                    @foreach ($timeline as $event)
                        <li class="relative">
                            <span @class(['absolute -start-5 top-1 size-[11px] rounded-full ring-2 ring-surface', 'bg-primary' => $loop->first, 'bg-success' => ! $loop->first])></span>
                            <p class="text-sm font-medium text-ink">{{ $event->to_status?->label() }}@if ($event->note) — <span class="font-normal">{{ \Illuminate\Support\Str::limit($event->note, 140) }}</span>@endif</p>
                            <p class="text-xs text-ink-secondary">{{ $event->created_at?->translatedFormat('j M, H:i') }}@if ($event->actor_name) · {{ $event->actor_name }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </x-ui::card>
        </div>

        <div class="grid content-start gap-6">
            <x-ui::card>
                <h2 class="mb-3 font-semibold text-ink">
                    @if ($orderUrl)<a href="{{ $orderUrl }}" class="hover:text-ink-brand">{{ __('after-sales::returns.order', ['number' => $order->number]) }}</a>@else{{ __('after-sales::returns.order', ['number' => $order->number]) }}@endif
                </h2>
                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.customer') }}</dt><dd class="font-medium text-ink">{{ $order->customer_name }}</dd></div>
                    @unless ($vendor)<div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.columns.store') }}</dt><dd class="font-medium text-ink">{{ $storeName }}</dd></div>@endunless
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.paid') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $order->money('grand_total')->format() }} · {{ \App\Modules\Commerce\DataTables\VendorOrdersDataTable::gatewayLabel($order->payment_gateway) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('after-sales::returns.window') }}</dt><dd class="font-medium text-ink">{{ __('after-sales::returns.window_value', ['days' => $windowDays, 'day' => $day]) }}</dd></div>
                </dl>
                @if ($return->refunds->isNotEmpty())
                    <ul class="mt-4 grid gap-2 border-t border-line pt-4 text-sm">
                        @foreach ($return->refunds as $refund)
                            <li class="flex items-center justify-between gap-3"><span dir="ltr">{{ $refund->reference }} · {{ $refund->money()->format() }}</span>@include('commerce::partials.status-badge', ['value' => $refund->status, 'context' => 'refund'])</li>
                        @endforeach
                    </ul>
                @endif
            </x-ui::card>

            <x-ui::card>
                <h2 class="mb-3 font-semibold text-ink">{{ __('after-sales::returns.messages') }}</h2>
                <ul class="grid gap-3">
                    <li class="max-w-[85%] rounded-card bg-subtle px-3 py-2 text-sm text-ink">
                        <p class="whitespace-pre-line">{{ $return->description }}</p>
                        <p class="mt-1 text-xs text-ink-secondary">{{ $return->customer_name ?? __('after-sales::returns.customer') }} · {{ $return->created_at?->translatedFormat('j M') }}</p>
                    </li>
                    @foreach ($messages as $message)
                        <li class="ms-auto max-w-[85%] rounded-card bg-primary px-3 py-2 text-sm text-on-primary">
                            <p class="whitespace-pre-line">{{ $message->note }}</p>
                            <p class="mt-1 text-xs opacity-80">{{ $message->actor_name }} · {{ $message->created_at?->translatedFormat('j M') }}</p>
                        </li>
                    @endforeach
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ $noteUrl }}" data-ajax class="mt-4 grid gap-2">
                        @csrf
                        <input type="hidden" name="customer_visible" value="1">
                        <x-ui::textarea name="note" :label="__('after-sales::returns.reply')" label-hidden :placeholder="__('after-sales::returns.reply_placeholder')" rows="2" maxlength="2000" required />
                        <div class="flex justify-end"><x-ui::button type="submit" size="sm" icon="message">{{ __('after-sales::returns.send') }}</x-ui::button></div>
                    </form>
                @endif
            </x-ui::card>

            <x-ui::card>
                <h2 class="mb-3 font-semibold text-ink">{{ __('after-sales::returns.internal_notes') }}</h2>
                @if ($notes->isEmpty())
                    <p class="text-sm text-ink-secondary">{{ __('after-sales::returns.no_notes') }}</p>
                @else
                    <ul class="grid gap-2">
                        @foreach ($notes as $note)
                            <li class="rounded-control bg-subtle px-3 py-2 text-sm"><p class="whitespace-pre-line text-ink">{{ $note->note }}</p><p class="mt-1 text-xs text-ink-secondary">{{ $note->actor_name }} · {{ $note->created_at?->translatedFormat('j M, H:i') }}</p></li>
                        @endforeach
                    </ul>
                @endif
                @if ($canManage)
                    <form method="POST" action="{{ $noteUrl }}" data-ajax class="mt-3 grid gap-2">
                        @csrf
                        <x-ui::textarea name="note" :label="__('after-sales::returns.add_note')" label-hidden :placeholder="__('after-sales::returns.note_placeholder')" rows="2" maxlength="2000" required />
                        <div><x-ui::button type="submit" variant="secondary" size="sm">{{ __('after-sales::returns.add_note') }}</x-ui::button></div>
                    </form>
                @endif
            </x-ui::card>
        </div>
    </div>
</div>

<x-ui::modal id="return-modal" :title="$title">
    @include('commerce::admin.partials.remote-states')
</x-ui::modal>
