@if (auth('admin')->user()?->can('storefront.template-parts.manage'))
    <div class="flex items-center justify-end">
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" data-remote-modal="theme-part-modal" :data-url="route('admin.storefront.template-parts.edit', $part)">{{ __('content::admin_themes.parts.edit') }}</x-ui::dropdown-item>
            @unless ($part->is_default)
                <x-ui::dropdown-item icon="star" :data-confirm="__('content::admin_themes.parts.default_message')" :data-confirm-title="__('content::admin_themes.parts.default_title', ['name' => $part->name, 'type' => mb_strtolower($part->type->label())])" data-confirm-tone="primary" :data-confirm-label="__('content::admin_themes.parts.make_default')" data-confirm-method="POST" :data-confirm-url="route('admin.storefront.template-parts.default', $part)">{{ __('content::admin_themes.parts.make_default') }}</x-ui::dropdown-item>
            @endunless
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('content::admin_themes.parts.delete_message')" :data-confirm-title="__('content::admin_themes.parts.delete_title', ['name' => $part->name])" :data-confirm-label="__('content::admin_themes.parts.delete')" data-confirm-method="DELETE" :data-confirm-url="route('admin.storefront.template-parts.destroy', $part)">{{ __('content::admin_themes.parts.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
