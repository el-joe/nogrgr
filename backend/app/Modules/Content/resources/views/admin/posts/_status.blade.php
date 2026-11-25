@if ($post->isScheduled())
    <x-ui::badge tone="info">{{ __('content::admin.posts.scheduled') }}</x-ui::badge>
@else
    <x-ui::badge :tone="$post->status->tone()">{{ $post->status->label() }}</x-ui::badge>
@endif
