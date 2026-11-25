@use('App\Modules\Content\Enums\PostStatus')
@php
    $visible = $post->status === PostStatus::Published && ! $post->isScheduled();
@endphp
<div class="flex items-center justify-end gap-1">
    @if (auth('admin')->user()?->can('content.posts.manage'))
        <x-ui::icon-button icon="edit" size="sm" :href="route('admin.content.posts.edit', $post)" :label="__('content::admin.common.edit')" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="route('admin.content.posts.edit', $post)">{{ __('content::admin.common.edit') }}</x-ui::dropdown-item>
            @if ($visible)
                <x-ui::dropdown-item icon="eye" :href="$post->url()" target="_blank" rel="noopener">{{ __('content::admin.common.view') }}</x-ui::dropdown-item>
            @endif
            @if ($post->status !== PostStatus::Published)
                <x-ui::dropdown-item icon="check" data-confirm-tone="brand" :data-confirm-label="__('content::admin.posts.publish')" data-confirm-method="POST" :data-confirm-url="route('admin.content.posts.publish', $post)" :data-confirm="__('content::admin.posts.publish')" :data-confirm-title="$post->title">{{ __('content::admin.posts.publish') }}</x-ui::dropdown-item>
            @endif
            @if ($post->status !== PostStatus::Archived)
                <x-ui::dropdown-item icon="box" data-confirm-tone="brand" :data-confirm-label="__('content::admin.posts.archive')" data-confirm-method="POST" :data-confirm-url="route('admin.content.posts.archive', $post)" :data-confirm="__('content::admin.posts.archive')" :data-confirm-title="$post->title">{{ __('content::admin.posts.archive') }}</x-ui::dropdown-item>
            @endif
            <x-ui::dropdown-divider />
            @include('content::admin.partials.delete-item', ['url' => route('admin.content.posts.destroy', $post), 'name' => $post->title])
        </x-ui::row-actions>
    @endif
</div>
