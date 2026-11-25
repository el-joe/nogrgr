@php($canManage = (bool) auth('admin')->user()?->can('localization.languages.manage'))
@if ($language->isCore())
    <span class="inline-flex items-center gap-2">
        <x-ui::status status="active" />
        <x-ui::icon name="key" :size="14" class="text-ink-muted" :label="__('localization::admin.languages.locked_hint')" />
    </span>
@elseif ($canManage)
    <x-ui::switch :name="'is_active_'.$language->id" field="is_active" :url="route('admin.localization.languages.toggle', $language)" :checked="$language->is_active" :aria-label="__('localization::admin.languages.toggle', ['name' => $language->name])" />
@else
    <x-ui::status :status="$language->is_active ? 'active' : 'inactive'" />
@endif
