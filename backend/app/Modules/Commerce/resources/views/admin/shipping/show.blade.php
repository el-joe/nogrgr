{{--
    Admin › Shipping queue › Shipment (ADM-022a, Figma Admin Panel 70:35864): status,
    create return / print label / update status, tracking card (carrier page link,
    timeline), items, attachments (upload / download / delete on the private central disk),
    ship-to address.
--}}
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('shipping-queue.manage');
    $address = $order->shipping_address ?? [];
    $country = $address['country_name'][app()->getLocale()] ?? $address['country_name']['en'] ?? null;
    $title = __('commerce::fulfilment.shipment_title', ['number' => $order->number]);
    $pattern = null;
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-orders">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.sales') => null, __('commerce::fulfilment.queue.title') => route('admin.shipping-queue.index'), '#'.$order->number => null]"
        >
            <x-slot:actions>
                @include('commerce::partials.status-badge', ['value' => $order->fulfilment_status])
                @if ($admin?->can('returns.manage') && in_array($order->status->value, ['shipped', 'delivered', 'completed'], true))
                    <x-ui::button variant="secondary" icon="return" data-remote-modal="shipping-modal" :data-url="route('admin.orders.returns.create', $fact->id)">{{ __('commerce::orders_admin.create_return') }}</x-ui::button>
                @endif
                <x-ui::button variant="secondary" icon="printer" :href="route('admin.shipping-queue.label', $fact->id)" target="_blank">{{ __('commerce::fulfilment.print_label') }}</x-ui::button>
                @if ($canManage && $order->fulfilment_status->transitions() !== [] && in_array($order->status->value, ['paid', 'processing', 'shipped'], true))
                    <x-ui::button icon="refresh" data-remote-modal="shipping-modal" :data-url="route('admin.shipping-queue.status.form', $fact->id)">{{ __('commerce::fulfilment.update_status') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid content-start gap-6">
                <x-ui::card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-ink">{{ __('commerce::fulfilment.tracking') }}</h2>
                        <a href="{{ route('admin.orders.show', $fact->id) }}" class="text-sm font-medium text-ink-brand hover:underline">{{ __('commerce::fulfilment.open_order') }}</a>
                    </div>
                    <dl class="mb-5 grid gap-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.company') }}</dt><dd class="font-semibold text-ink">{{ $order->shipping_company_name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.tracking') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $order->tracking_number ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.estimate') }}</dt><dd class="font-semibold text-ink">{{ $order->delivery_min_date ? $order->delivery_min_date->translatedFormat('j M').' – '.$order->delivery_max_date?->translatedFormat('j M') : '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-secondary">{{ __('commerce::fulfilment.fields.branch') }}</dt><dd class="font-semibold text-ink">{{ $order->fulfilment_branch_id ? ($branches[$order->fulfilment_branch_id] ?? '—') : '—' }}</dd></div>
                    </dl>
                    @include('commerce::partials.tracking', ['order' => $order])
                </x-ui::card>

                <x-ui::card>
                    <h2 class="mb-3 font-semibold text-ink">{{ __('commerce::orders_admin.items', ['count' => $order->items->count()]) }}</h2>
                    @include('commerce::partials.items', ['order' => $order])
                </x-ui::card>
            </div>

            <div class="grid content-start gap-6">
                <x-ui::card>
                    <h2 class="mb-3 font-semibold text-ink">{{ __('commerce::fulfilment.attachments.title') }}</h2>
                    @if ($attachments->isEmpty())
                        <p class="text-sm text-ink-secondary">{{ __('commerce::fulfilment.attachments.empty') }}</p>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($attachments as $file)
                                <li class="flex items-center gap-3 py-2.5">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="str_starts_with((string) $file->mime, 'image/') ? 'image' : 'file'" :size="18" /></span>
                                    <a href="{{ route('admin.shipping-queue.attachments.show', [$fact->id, $file->id]) }}" class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-ink">{{ $file->original_name }}</span>
                                        <span class="text-xs text-ink-secondary">{{ $file->kind->label() }} · {{ \Illuminate\Support\Number::fileSize($file->size) }}</span>
                                    </a>
                                    @if ($canManage)
                                        <x-ui::icon-button icon="trash" size="sm" :label="__('commerce::fulfilment.attachments.delete', ['name' => $file->original_name])" data-confirm="{{ __('commerce::fulfilment.attachments.delete_confirm') }}" data-confirm-method="DELETE" :data-confirm-url="route('admin.shipping-queue.attachments.destroy', [$fact->id, $file->id])" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($canManage)
                        <form method="POST" action="{{ route('admin.shipping-queue.attachments.store', $fact->id) }}" data-ajax enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-line pt-4">
                            @csrf
                            <x-ui::select name="kind" :label="__('commerce::fulfilment.attachments.kind')" :options="$kinds" :search="false" required />
                            <x-ui::file-upload name="file" :label="__('commerce::fulfilment.attachments.file')" accept=".pdf,.jpg,.jpeg,.png,.webp" :hint="__('commerce::fulfilment.attachments.hint')" required />
                            <div><x-ui::button type="submit" variant="secondary" icon="upload">{{ __('commerce::fulfilment.attachments.upload') }}</x-ui::button></div>
                        </form>
                    @endif
                </x-ui::card>

                <x-ui::card>
                    <h2 class="mb-3 font-semibold text-ink">{{ __('commerce::fulfilment.ship_to') }}</h2>
                    <address class="grid gap-0.5 text-sm not-italic text-ink">
                        <span>{{ $address['full_name'] ?? $order->customer_name }}</span>
                        <span>{{ $address['address_line1'] ?? '' }}</span>
                        <span>{{ collect([$address['city'] ?? null, $address['state'] ?? null, $country])->filter()->implode(', ') }}</span>
                        <span dir="ltr">{{ $address['phone'] ?? $order->customer_phone }}</span>
                    </address>
                </x-ui::card>
            </div>
        </div>
    </div>

    <x-ui::modal id="shipping-modal" :title="$title">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
