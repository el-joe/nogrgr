{{-- Row actions: open, and for managers cancel (manufacturing) / delete (pending brand). --}}
@php
    $kind = $request->kind();
    $showUrl = vendor_route('requests.'.$kind->value.'.show', ['id' => $request->id]);
    $canManage = (bool) auth('vendor')->user()?->can($kind->permission('manage'));
    $cancellable = $canManage && $request instanceof \App\Modules\Catalog\Models\Requests\ManufacturingRequest && $request->status->isCancellableByVendor();
    $deletable = $canManage && $request instanceof \App\Modules\Catalog\Models\Requests\BrandRequest && $request->status === \App\Modules\Catalog\Enums\BrandRequestStatus::Pending;
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="$showUrl" :label="__('catalog::vendor_requests.actions.view', ['reference' => $request->reference()])" />
    @if ($cancellable || $deletable)
        <x-ui::row-actions :label="$request->reference()">
            @if ($cancellable)
                <x-ui::dropdown-item icon="x-circle" tone="danger"
                    :data-confirm="__('catalog::vendor_requests.actions.cancel_message')"
                    :data-confirm-title="__('catalog::vendor_requests.actions.cancel_title', ['reference' => $request->reference()])"
                    data-confirm-method="POST"
                    :data-confirm-url="vendor_route('requests.manufacturing.cancel', ['id' => $request->id])"
                >{{ __('catalog::vendor_requests.actions.cancel') }}</x-ui::dropdown-item>
            @endif
            @if ($deletable)
                <x-ui::dropdown-item icon="trash" tone="danger"
                    :data-confirm="__('catalog::vendor_requests.actions.delete_message')"
                    :data-confirm-title="__('catalog::vendor_requests.actions.delete_title', ['reference' => $request->reference()])"
                    data-confirm-method="DELETE"
                    :data-confirm-url="vendor_route('requests.brand.destroy', ['id' => $request->id])"
                >{{ __('catalog::vendor_requests.actions.delete') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
