{{--
    Category × channel matrix (ADM-007a). Unchecked boxes post 0 through their hidden
    input so the full matrix is always submitted.
--}}
<form method="POST" action="{{ route('admin.notifications.preferences.update') }}" data-ajax novalidate class="grid gap-4">
    @csrf
    @method('PUT')
    <div class="overflow-x-auto rounded-control border border-line">
        <table class="w-full text-[13px]">
            <thead class="bg-subtle text-start text-xs text-ink-secondary">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-start font-medium">{{ __('notifications::panel.preferences.event') }}</th>
                    @foreach ($channels as $channel)
                        <th scope="col" class="px-3 py-2.5 text-center font-medium" title="{{ __('notifications::panel.preferences.channel_help.'.$channel->value) }}">{{ $channel->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr class="border-t border-line">
                        <th scope="row" class="px-4 py-3 text-start font-normal text-ink">
                            <span class="flex items-center gap-2">
                                <x-ui::icon :name="$category->icon()" :size="16" class="text-ink-muted" />
                                {{ $category->label() }}
                            </span>
                        </th>
                        @foreach ($channels as $channel)
                            <td class="px-3 py-3 text-center">
                                <x-ui::checkbox
                                    :name="'preferences['.$category->value.']['.$channel->value.']'"
                                    :id="($idPrefix ?? 'pref').'-'.$category->value.'-'.$channel->value"
                                    :checked="$matrix[$category->value][$channel->value]"
                                    unchecked-value="0"
                                    :aria-label="__('notifications::panel.preferences.toggle', ['channel' => $channel->label(), 'category' => $category->label()])"
                                />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <ul class="grid gap-1 text-xs text-ink-secondary">
        @foreach ($channels as $channel)
            <li><span class="font-medium text-ink">{{ $channel->label() }}:</span> {{ __('notifications::panel.preferences.channel_help.'.$channel->value) }}</li>
        @endforeach
    </ul>
    <div class="flex justify-end">
        <x-ui::button type="submit" icon="check">{{ __('notifications::panel.preferences.save') }}</x-ui::button>
    </div>
</form>
