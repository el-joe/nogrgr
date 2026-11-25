{{--
    Legacy @extends entry for account views: wraps @section('account') in the family
    account shell (x-sf::account.layout). New views use the component directly.
--}}
<x-sf::account.layout :title="$title" :active="$active ?? null">
    @yield('account')
</x-sf::account.layout>
