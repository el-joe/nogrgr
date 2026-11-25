@if (auth('admin')->user()?->can('localization.countries.manage'))
    <x-ui::switch :name="'is_active_'.$country->id" field="is_active" :url="route('admin.localization.countries.toggle', $country)" :checked="$country->is_active" :aria-label="__('localization::admin.countries.toggle', ['name' => $country->name])" />
@else
    <x-ui::status :status="$country->is_active ? 'active' : 'inactive'" />
@endif
