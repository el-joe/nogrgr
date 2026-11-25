{{-- Domain row: DNS check page, edit (pending only), check again, remove. --}}
@use('App\Modules\Platform\Enums\DomainRequestStatus')
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('owner.domains.show', $request->id)" :label="__('platform::owner.domains.view', ['host' => $request->host])" />
    @if ($request->status === DomainRequestStatus::Pending)
        <x-ui::icon-button icon="edit" size="sm" data-remote-modal="domain-edit-modal" :data-url="route('owner.domains.edit', $request->id)" :label="__('platform::owner.domains.edit', ['host' => $request->host])" />
    @endif
    @if ($request->status !== DomainRequestStatus::Removed)
        <x-ui::row-actions :label="__('platform::owner.domains.more', ['host' => $request->host])">
            <x-ui::dropdown-item icon="refresh" data-domain-check :data-url="route('owner.domains.check', $request->id)">{{ __('platform::owner.domains.check_again') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="trash" tone="danger" :href="route('owner.domains.destroy', $request->id)" data-confirm-method="DELETE" :data-confirm="__('platform::owner.domains.remove_confirm', ['host' => $request->host])" :data-confirm-title="__('platform::owner.domains.remove_title')">{{ __('platform::owner.domains.remove') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
