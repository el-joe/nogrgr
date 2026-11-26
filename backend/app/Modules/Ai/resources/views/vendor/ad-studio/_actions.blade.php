{{-- Advertising Library row actions: Preview / Download (ready), Try again (failed), Delete (finished). --}}
@use('App\Modules\Ai\Enums\VideoAdStatus')
@if ($ad->isReady() || ($canManage && ! $ad->status->isActive()))
    <x-ui::row-actions>
        @if ($ad->isReady())
            <x-ui::dropdown-item icon="eye" data-ad-preview="{{ $ad->id }}">{{ __('ai::ad_studio.library.preview') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="download" :href="vendor_route('marketing.ads.download', ['ad' => $ad->id])" download>{{ __('ai::ad_studio.library.download') }}</x-ui::dropdown-item>
        @endif
        @if ($canManage && $ad->status === VideoAdStatus::Failed)
            <x-ui::dropdown-item icon="refresh" :href="vendor_route('marketing.ads.retry', ['ad' => $ad->id])" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('ai::ad_studio.library.retry')" :data-confirm="__('ai::ad_studio.uses')">{{ __('ai::ad_studio.library.retry') }}</x-ui::dropdown-item>
        @endif
        @if ($canManage && ! $ad->status->isActive())
            <x-ui::dropdown-item icon="trash" tone="danger" :href="vendor_route('marketing.ads.destroy', ['ad' => $ad->id])" data-confirm-method="DELETE" :data-confirm-title="__('ai::ad_studio.library.delete_title')" :data-confirm="__('ai::ad_studio.library.delete_message')">{{ __('ai::ad_studio.library.delete') }}</x-ui::dropdown-item>
        @endif
    </x-ui::row-actions>
@else
    <span class="text-ink-muted">—</span>
@endif
