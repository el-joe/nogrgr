{{--
    Storefront header per theme family (Ecommet 1:411, Elora 1:20867, Souqify 1:81294).
    Links, search, cart and favourites only render once their routes exist (no dead links).
    On phones the family's compact bar is shown; account-style pages pass $mobileTitle to
    get the "‹ Title  🛒" app bar from the Storefront additions frames instead.
--}}
@props(['family', 'mobileTitle' => null, 'back' => null])
<x-dynamic-component :component="'sf::header.'.$family" :mobile-title="$mobileTitle" :back="$back" />
