<div class="flex items-center justify-end gap-1">
    @if (auth('admin')->user()?->can('plans.manage'))
        <x-ui::icon-button icon="edit" size="sm" :href="route('admin.plans.edit', $plan)" :label="__('billing::plans.edit')" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.plans.edit', $plan)">{{ __('billing::plans.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item
                icon="trash"
                tone="danger"
                :data-confirm="($plan->open_subscriptions_count ?? 0) > 0 ? trans_choice('billing::plans.in_use', $plan->open_subscriptions_count, ['count' => $plan->open_subscriptions_count]) : __('billing::plans.confirm_delete_message')"
                :data-confirm-title="__('billing::plans.confirm_delete_title', ['name' => $plan->name])"
                data-confirm-method="DELETE"
                :data-confirm-url="route('admin.plans.destroy', $plan)"
            >{{ __('ui.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
