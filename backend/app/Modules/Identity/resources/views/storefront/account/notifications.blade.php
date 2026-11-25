{{--
    Account › Notification preferences (Figma Storefront additions §8): one switch per
    type × channel. Inputs: $preferences (type => channel => bool), $types, $channels.
--}}
<x-sf::account.layout :title="__('identity::account.notifications.title')" active="notifications" page="notifications">
    <p class="text-sm text-sf-muted">{{ __('identity::account.notifications.intro') }}</p>
    <form method="POST" action="{{ route('storefront.account.notifications.update') }}" class="grid gap-5" data-ajax data-notification-preferences-form>
        @csrf
        @method('PUT')
        <div class="sf-card overflow-x-auto px-5 py-3">
            <table class="w-full min-w-[420px]">
                <thead>
                    <tr class="border-b border-sf-border text-xs text-sf-muted">
                        <th scope="col" class="py-3 text-start font-normal">{{ __st('account.notification_type') }}</th>
                        @foreach ($channels as $channel)
                            <th scope="col" class="w-20 py-3 text-center font-normal">{{ $channel->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $type)
                        <tr class="border-b border-sf-border last:border-b-0">
                            <th scope="row" class="py-3 text-start font-normal">
                                <span class="block text-sm text-sf-ink">{{ $type->label() }}</span>
                                <span class="block text-xs text-sf-muted">{{ __('identity::account.notifications.descriptions.'.$type->value) }}</span>
                            </th>
                            @foreach ($channels as $channel)
                                <td class="py-3 text-center">
                                    <input type="hidden" name="preferences[{{ $type->value }}][{{ $channel->value }}]" value="0">
                                    <input type="checkbox" role="switch" class="sf-switch" name="preferences[{{ $type->value }}][{{ $channel->value }}]" value="1" @checked($preferences[$type->value][$channel->value]) aria-label="{{ $type->label() }} — {{ $channel->label() }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div><x-sf::form.button type="submit">{{ __st('account.save_preferences') }}</x-sf::form.button></div>
    </form>
</x-sf::account.layout>
