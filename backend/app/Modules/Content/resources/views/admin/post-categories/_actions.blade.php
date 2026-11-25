<div class="flex items-center justify-end gap-1">
    @if (auth('admin')->user()?->can('content.post-categories.manage'))
        <x-ui::icon-button icon="edit" size="sm" :href="route('admin.content.post-categories.edit', $category)" :label="__('content::admin.common.edit')" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.content.post-categories.edit', $category)">{{ __('content::admin.common.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            @include('content::admin.partials.delete-item', ['url' => route('admin.content.post-categories.destroy', $category), 'name' => $category->name, 'message' => __('content::admin.categories.delete_message')])
        </x-ui::row-actions>
    @endif
</div>
