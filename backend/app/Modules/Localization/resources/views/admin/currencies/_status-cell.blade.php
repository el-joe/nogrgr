@if ($currency->isBase())
    <span class="inline-flex items-center gap-2">
        <x-ui::badge tone="brand" dot>{{ __('localization::admin.currencies.base') }}</x-ui::badge>
        <x-ui::icon name="key" :size="14" class="text-ink-muted" :label="__('localization::admin.currencies.locked')" />
    </span>
@elseif (auth('admin')->user()?->can('localization.currencies.manage'))
    <x-ui::switch :name="'is_active_'.$currency->id" field="is_active" :url="route('admin.localization.currencies.toggle', $currency)" :checked="$currency->is_active" :aria-label="__('localization::admin.currencies.toggle', ['name' => $currency->name])" />
@else
    <x-ui::status :status="$currency->is_active ? 'active' : 'inactive'" />
@endif
