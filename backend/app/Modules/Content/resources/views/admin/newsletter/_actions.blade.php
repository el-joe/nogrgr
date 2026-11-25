<div class="flex items-center justify-end gap-1">
    @if (auth('admin')->user()?->can('content.newsletter.manage'))
        <x-ui::row-actions>
            @include('content::admin.partials.delete-item', ['url' => route('admin.content.newsletter.destroy', $subscriber), 'name' => $subscriber->email, 'message' => __('content::admin.newsletter.delete_message')])
        </x-ui::row-actions>
    @endif
</div>
