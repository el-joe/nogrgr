{{-- Row actions: view, and for managers change status (modal) / archive-restore (product). --}}
@php
    $canManage = (bool) auth('admin')->user()?->can($kind->permission('manage'));
    $showUrl = route($kind->routePrefix().'.show', $request->id);
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="$showUrl" :label="__('catalog::requests.actions.view', ['reference' => $request->reference()])" />
    @if ($canManage)
        <x-ui::icon-button
            icon="edit"
            size="sm"
            :label="__('catalog::requests.actions.change_status')"
            data-request-status
            :data-url="route($kind->routePrefix().'.status', $request->id)"
            :data-current="$request->status->value"
            :data-reference="$request->reference()"
        />
        <x-ui::row-actions :label="__('catalog::requests.actions.more', ['reference' => $request->reference()])">
            <x-ui::dropdown-item icon="eye" :href="$showUrl">{{ __('catalog::requests.actions.open') }}</x-ui::dropdown-item>
            @if ($kind === \App\Modules\Catalog\Enums\RequestKind::Product)
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item
                    :icon="$request->archived_at ? 'refresh' : 'box'"
                    data-request-action
                    :data-url="route('admin.requests.products.archive', $request->id)"
                    data-method="PATCH"
                    :data-payload="json_encode(['archived' => $request->archived_at ? 0 : 1])"
                    :data-confirm-text="$request->archived_at ? null : __('catalog::requests.archive_confirm')"
                    :data-confirm-title="$request->archived_at ? null : __('catalog::requests.actions.archive').' '.$request->reference().'?'"
                >{{ $request->archived_at ? __('catalog::requests.actions.restore') : __('catalog::requests.actions.archive') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
