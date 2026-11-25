@if (auth('admin')->user()?->can('localization.countries.manage'))
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('localization::admin.edit_named', ['name' => $country->name])" :href="route('admin.localization.countries.edit', $country)" />
    </div>
@endif
