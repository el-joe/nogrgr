{{-- Status pill + on / off switch (PATCH). --}}
<div class="flex items-center justify-end gap-3">
    <x-ui::status :status="$part->is_active ? 'active' : 'inactive'">{{ $part->is_active ? __('content::admin_themes.active') : __('content::admin_themes.inactive') }}</x-ui::status>
    @if (auth('admin')->user()?->can('storefront.template-parts.manage'))
        <x-ui::switch :name="'is_active_'.$part->id" :id="'theme-part-active-'.$part->id" :checked="$part->is_active" :url="route('admin.storefront.template-parts.toggle', $part)" field="is_active" :aria-label="__('content::admin_themes.parts.active_named', ['name' => $part->name])" />
    @endif
</div>
