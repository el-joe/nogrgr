<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('catalog::vendor.edit_requests.view', ['number' => 'ER-'.$request->id])" data-remote-modal="edit-request-detail" :data-url="vendor_route('catalog.edit-requests.show', ['editRequest' => $request->id])" />
</div>
