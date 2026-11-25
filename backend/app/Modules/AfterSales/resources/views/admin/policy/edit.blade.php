{{--
    Admin › Storefront › Return policy (ADM-090, Figma Admin Panel 78:81991): platform
    default rules (window, restocking fee, return shipping payer, non-returnable
    categories, video evidence, exchanges) and the reasons list (add / edit modal, active
    switch, delete). Stores may tighten the policy but not loosen it.
--}}
<x-layouts.panel surface="admin" :title="__('after-sales::policy.title')" data-page="admin-orders">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('after-sales::policy.title')" :subtitle="__('after-sales::policy.subtitle')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('after-sales::policy.title') => null]" />

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui::card>
                <h2 class="mb-4 font-semibold text-ink">{{ __('after-sales::policy.rules') }}</h2>
                <form method="POST" action="{{ route('admin.storefront.return-policy.update') }}" data-ajax class="grid gap-4">
                    @csrf
                    @method('PUT')
                    <x-ui::input name="window_days" type="number" min="0" max="365" :label="__('after-sales::policy.fields.window')" :value="$policy->window_days" required />
                    <x-ui::input name="restocking_fee" type="number" step="0.01" min="0" max="100" :label="__('after-sales::policy.fields.fee')" :value="number_format($policy->restocking_fee_bps / 100, 2, '.', '')" :helper="__('after-sales::policy.fields.fee_help')" required />
                    <x-ui::select name="return_shipping_payer" :label="__('after-sales::policy.fields.payer')" :options="$payers" :selected="$policy->return_shipping_payer->value" :search="false" required />
                    <x-ui::select name="non_returnable_category_ids[]" multiple :label="__('after-sales::policy.fields.categories')" :options="$categories" :selected="$policy->non_returnable_category_ids ?? []" :placeholder="__('after-sales::policy.fields.categories_placeholder')" />
                    <x-ui::switch name="require_video" :label="__('after-sales::policy.fields.video')" :checked="$policy->require_video" />
                    <x-ui::switch name="allow_exchanges" :label="__('after-sales::policy.fields.exchanges')" :checked="$policy->allow_exchanges" />
                    <div class="flex justify-end"><x-ui::button type="submit" icon="check">{{ __('after-sales::common.save_changes') }}</x-ui::button></div>
                </form>
            </x-ui::card>

            <x-ui::card class="content-start">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-ink">{{ __('after-sales::policy.reasons.title') }}</h2>
                    <x-ui::button variant="secondary" size="sm" icon="plus" data-remote-modal="reason-modal" :data-url="route('admin.storefront.return-policy.reasons.create')">{{ __('after-sales::policy.reasons.add_short') }}</x-ui::button>
                </div>
                @if ($reasons->isEmpty())
                    <x-ui::empty-state compact icon="list" :title="__('after-sales::policy.reasons.empty')" />
                @else
                    <ul class="grid gap-2">
                        @foreach ($reasons as $reason)
                            <li class="flex items-center gap-3 rounded-control border border-line px-3 py-2.5">
                                <span class="w-5 text-center text-xs text-ink-secondary">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $reason->name }}</p>
                                    <p class="text-xs text-ink-secondary">{{ collect([$reason->requires_video ? __('after-sales::policy.reasons.video_required') : null, $reason->seller_fault ? __('after-sales::policy.reasons.seller_fault') : __('after-sales::policy.reasons.fee_applies')])->filter()->implode(' · ') }}</p>
                                </div>
                                <x-ui::switch :name="'active_'.$reason->id" :label="__('after-sales::policy.reasons.active')" :checked="$reason->is_active" :url="route('admin.storefront.return-policy.reasons.toggle', $reason->id)" field="is_active" />
                                <x-ui::row-actions>
                                    <x-ui::dropdown-item icon="edit" data-remote-modal="reason-modal" :data-url="route('admin.storefront.return-policy.reasons.edit', $reason->id)">{{ __('after-sales::policy.reasons.edit') }}</x-ui::dropdown-item>
                                    <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('after-sales::policy.reasons.delete_confirm')" :data-confirm-title="__('after-sales::policy.reasons.delete_title', ['name' => $reason->name])" data-confirm-method="DELETE" :data-confirm-url="route('admin.storefront.return-policy.reasons.destroy', $reason->id)">{{ __('after-sales::policy.reasons.delete') }}</x-ui::dropdown-item>
                                </x-ui::row-actions>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui::card>
        </div>
    </div>

    <x-ui::modal id="reason-modal" :title="__('after-sales::policy.reasons.add')">
        @include('commerce::admin.partials.remote-states')
    </x-ui::modal>
</x-layouts.panel>
