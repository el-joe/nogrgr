{{--
    Central website page shell for the registration completion pages: Figma navbar
    (pill, 1258 wide) + brand glow + footer. Inputs: $title, $page (data-page hook
    that lazy-loads resources/js/central/register.js).
--}}
<x-layouts.central :title="$title">
    <div class="reg-page relative flex min-h-screen flex-col overflow-x-clip bg-white font-sans text-ink" data-page="{{ $page }}">
        <span class="reg-glow max-lg:hidden" aria-hidden="true"></span>
        @include('platform::central.register.partials.header')
        <main id="main" class="relative flex-1">
            @yield('content')
        </main>
        @include('platform::central.register.partials.footer')
    </div>
</x-layouts.central>
