{{--
    Vendor request detail with the shared live conversation:
     - Product request (VEN-050c, Vendor Panel (new screens) 87:109541): conversation with
       the platform, Request card (status, priority, created) and Files card.
     - Manufacturing (VEN-050f, 87:110114): pipeline stepper, "Cancel request", Payment
       request card with Pay.
     - Brand request (VEN-050i, 87:110114): same without the stepper and cancel.
--}}
@use('App\Modules\Catalog\Enums\RequestKind')
@use('App\Modules\Catalog\Enums\PaymentRequestStatus')
@php
    $key = $kind->value;
    $title = $request->reference().' · '.$request->subject();
    $locale = app()->getLocale();
    $cancellable = $canManage && $kind === RequestKind::Manufacturing && $request->status->isCancellableByVendor();
@endphp
<x-vendor::layout :title="$title" page="vendor-requests">
    <div class="grid gap-6" data-request-detail data-reload-on-change>
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('catalog::vendor_requests.title') => vendor_route('requests.index'), __('catalog::vendor_requests.tabs.'.$key) => vendor_route('requests.'.$key.'.index'), $request->reference() => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$request->statusTone()" dot data-request-status-badge>{{ $request->statusLabel() }}</x-ui::badge>
            </x-slot:status>
            @if ($cancellable)
                <x-slot:actions>
                    <x-ui::button variant="secondary" icon="x"
                        :data-confirm="__('catalog::vendor_requests.actions.cancel_message')"
                        :data-confirm-title="__('catalog::vendor_requests.actions.cancel_title', ['reference' => $request->reference()])"
                        data-confirm-method="POST"
                        :data-confirm-url="vendor_route('requests.manufacturing.cancel', ['id' => $request->id])"
                    >{{ __('catalog::vendor_requests.actions.cancel') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($kind === RequestKind::Manufacturing)
            @include('catalog::admin.requests.threads._stepper', ['status' => $request->status])
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid min-w-0 gap-6">
                @if (filled($request->description))
                    <x-ui::card :title="__('catalog::vendor_requests.details.description')" id="request-description">
                        <p class="whitespace-pre-line text-sm text-ink">{{ $request->description }}</p>
                    </x-ui::card>
                @endif
                @include('catalog::admin.requests.thread._conversation', [
                    'request' => $request,
                    'messages' => $messages,
                    'canManage' => $canManage,
                    'realtime' => $realtime,
                    'viewer' => 'vendor',
                    'messagesUrl' => vendor_route('requests.'.$key.'.messages', ['id' => $request->id]),
                    'replyUrl' => vendor_route('requests.'.$key.'.reply', ['id' => $request->id]),
                    'threadTitle' => __('catalog::vendor_requests.thread_title', ['app' => config('app.name')]),
                ])
            </div>

            <div class="grid min-w-0 gap-6">
                @if ($kind->hasPayments())
                    @include('catalog::vendor.requests._payments')
                @endif

                @include('catalog::vendor.requests._details')

                <x-ui::card :title="__('catalog::vendor_requests.files.title')" id="request-files">
                    @forelse ($attachments as $attachment)
                        <a href="{{ vendor_route('requests.attachments.show', ['attachment' => $attachment->id, 'download' => 1]) }}" class="flex items-center gap-3 border-b border-line py-2.5 last:border-b-0 hover:bg-subtle" aria-label="{{ __('catalog::requests.files.download', ['name' => $attachment->original_name]) }}">
                            <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="$attachment->isImage() ? 'image' : 'file'" :size="18" /></span>
                            <span class="grid min-w-0">
                                <span class="truncate text-sm font-medium text-ink">{{ $attachment->original_name }}</span>
                                <span class="text-xs text-ink-secondary">{{ $attachment->humanSize() }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm text-ink-secondary">{{ __('catalog::vendor_requests.files.empty') }}</p>
                    @endforelse
                </x-ui::card>
            </div>
        </div>
    </div>
</x-vendor::layout>
