<x-layouts.panel surface="vendor" :title="__('platform::panel.dashboard')">
    <h1 class="text-2xl font-semibold">{{ __('platform::panel.vendor_dashboard', ['store' => $tenantName]) }}</h1>
    <nav class="mt-4 flex gap-4">
        <a href="{{ vendor_route('dashboard') }}" data-nav="dashboard">{{ __('platform::panel.dashboard') }}</a>
        <form method="POST" action="{{ vendor_route('logout') }}" data-logout>
            @csrf
            <button type="submit">{{ __('identity::auth.logout') }}</button>
        </form>
    </nav>
</x-layouts.panel>
