@use('App\Modules\Content\Enums\PageStatus')
<div class="flex items-center justify-end gap-1">
    @if (auth('admin')->user()?->can('content.pages.manage'))
        <x-ui::icon-button icon="edit" size="sm" :href="route('admin.content.pages.edit', $page)" :label="__('content::admin.common.edit')" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.content.pages.edit', $page)">{{ __('content::admin.common.edit') }}</x-ui::dropdown-item>
            @if ($page->status === PageStatus::Active && $page->isOnCentral())
                <x-ui::dropdown-item icon="eye" :href="$page->url()" target="_blank" rel="noopener">{{ __('content::admin.common.view') }}</x-ui::dropdown-item>
            @endif
            @if ($page->status !== PageStatus::Active)
                <x-ui::dropdown-item icon="check-circle" data-confirm-tone="brand" :data-confirm-label="__('content::admin.pages.activate')" :data-confirm="__('content::admin.pages.activate')" :data-confirm-title="$page->title" data-confirm-method="POST" :data-confirm-url="route('admin.content.pages.activate', $page)">{{ __('content::admin.pages.activate') }}</x-ui::dropdown-item>
            @endif
            @if ($page->status !== PageStatus::Archived)
                <x-ui::dropdown-item icon="box" data-confirm-tone="brand" :data-confirm-label="__('content::admin.pages.archive')" :data-confirm="__('content::admin.pages.archive')" :data-confirm-title="$page->title" data-confirm-method="POST" :data-confirm-url="route('admin.content.pages.archive', $page)">{{ __('content::admin.pages.archive') }}</x-ui::dropdown-item>
            @endif
            @unless ($page->is_system)
                <x-ui::dropdown-divider />
                @include('content::admin.partials.delete-item', ['url' => route('admin.content.pages.destroy', $page), 'name' => $page->title])
            @endunless
        </x-ui::row-actions>
    @endif
</div>
