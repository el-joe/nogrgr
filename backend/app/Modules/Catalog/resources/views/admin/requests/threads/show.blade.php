{{--
    Request detail with the shared conversation:
     - Product request (ADM-018d, Figma 68:31430): header Archive / Restore, Details card
       with Status + Priority selects, request facts and the Files card.
     - Manufacturing (ADM-018f, 68:33125): pipeline stepper, header "Change status" +
       "Request payment", Payment requests card and Internal notes.
     - Brand request (ADM-018h, 68:33923): same as manufacturing without the stepper.
    Managers only (requests.<kind>.manage) see the action controls.
--}}
@use('App\Modules\Catalog\Enums\RequestKind')
@use('App\Modules\Catalog\Enums\RequestPriority')
@use('App\Modules\Catalog\Enums\ManufacturingRequestStatus')
@use('App\Modules\Catalog\Enums\PaymentRequestStatus')
@php
    $prefix = $kind->routePrefix();
    $title = $request->reference().' · '.$request->subject();
    $tabKey = match ($kind) { RequestKind::Product => 'products', RequestKind::Manufacturing => 'manufacturing', RequestKind::Brand => 'brands' };
    $locale = app()->getLocale();
    $store = $request->tenant;
    $isProduct = $kind === RequestKind::Product;
    $realtime = app(\App\Modules\Catalog\Services\Requests\RequestThreadService::class)->realtimeConfig($request);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-requests">
    <div class="grid gap-6" data-request-detail data-reload-on-change>
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('catalog::requests.breadcrumb') => route('admin.requests.index'), __('catalog::requests.tabs.'.$tabKey) => route($prefix.'.index'), $request->reference() => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$request->statusTone()" data-request-status-badge>{{ $request->statusLabel() }}</x-ui::badge>
                @if ($isProduct && $request->archived_at)
                    <x-ui::badge tone="neutral" variant="outline">{{ __('catalog::requests.states.archived') }}</x-ui::badge>
                @endif
            </x-slot:status>
            @if ($canManage)
                <x-slot:actions>
                    @if ($isProduct)
                        <x-ui::button
                            variant="secondary"
                            :icon="$request->archived_at ? 'refresh' : 'box'"
                            data-request-action
                            :data-url="route($prefix.'.archive', $request->id)"
                            data-method="PATCH"
                            :data-payload="json_encode(['archived' => $request->archived_at ? 0 : 1])"
                            :data-confirm-text="$request->archived_at ? null : __('catalog::requests.archive_confirm')"
                            :data-confirm-title="$request->archived_at ? null : __('catalog::requests.actions.archive').' '.$request->reference().'?'"
                        >{{ $request->archived_at ? __('catalog::requests.actions.restore') : __('catalog::requests.actions.archive') }}</x-ui::button>
                    @else
                        <x-ui::button variant="secondary" icon="refresh" data-request-status :data-url="route($prefix.'.status', $request->id)" :data-current="$request->status->value">{{ __('catalog::requests.actions.change_status') }}</x-ui::button>
                        <x-ui::button icon="credit-card" data-overlay-open="payment-request-modal">{{ __('catalog::requests.actions.request_payment') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($kind === RequestKind::Manufacturing)
            @include('catalog::admin.requests.threads._stepper', ['status' => $request->status])
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid min-w-0 gap-6">
                @if (filled($request->description))
                    <x-ui::card :title="__('catalog::requests.details.description')" id="request-description">
                        <p class="whitespace-pre-line text-sm text-ink">{{ $request->description }}</p>
                    </x-ui::card>
                @endif
                @include('catalog::admin.requests.thread._conversation', ['request' => $request, 'messages' => $messages, 'canManage' => $canManage, 'realtime' => $realtime])
            </div>

            <div class="grid min-w-0 gap-6">
                @include('catalog::admin.requests.threads._details', ['request' => $request, 'kind' => $kind, 'store' => $store, 'canManage' => $canManage, 'statuses' => $statuses])

                @if ($kind->hasPayments())
                    @include('catalog::admin.requests.threads._payments', ['request' => $request, 'payments' => $payments, 'canManage' => $canManage])
                    @include('catalog::admin.requests.threads._notes', ['request' => $request, 'canManage' => $canManage])
                @endif

                <x-ui::card :title="__('catalog::requests.files.title', ['count' => $attachments->count()])" id="request-files">
                    @forelse ($attachments as $attachment)
                        <a href="{{ route('admin.requests.attachments.show', ['attachment' => $attachment->id, 'download' => 1]) }}" class="flex items-center gap-3 border-b border-line py-2.5 last:border-b-0 hover:bg-subtle" aria-label="{{ __('catalog::requests.files.download', ['name' => $attachment->original_name]) }}">
                            <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="$attachment->isImage() ? 'image' : 'file'" :size="18" /></span>
                            <span class="grid min-w-0">
                                <span class="truncate text-sm font-medium text-ink">{{ $attachment->original_name }}</span>
                                <span class="text-xs text-ink-secondary">{{ $attachment->humanSize() }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm text-ink-secondary">{{ __('catalog::requests.empty.files') }}</p>
                    @endforelse
                </x-ui::card>
            </div>
        </div>
    </div>

    @if ($canManage && ! $isProduct)
        @include('catalog::admin.requests.threads._status-modal', ['statuses' => $statuses, 'action' => route($prefix.'.status', $request->id), 'current' => $request->status->value])
        @include('catalog::admin.requests.threads._payment-modal', ['request' => $request])
    @endif
</x-layouts.panel>
