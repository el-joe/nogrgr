@if (auth('admin')->user()?->can('shipping.companies.manage'))
    <x-ui::switch :name="'is_active_'.$company->id" field="is_active" :url="route('admin.shipping.companies.toggle', $company)" :checked="$company->is_active" :aria-label="__('shipping::companies.toggle', ['name' => $company->name])" />
@else
    <x-ui::status :status="$company->is_active ? 'active' : 'inactive'">{{ $company->is_active ? __('shipping::common.on') : __('shipping::common.off') }}</x-ui::status>
@endif
