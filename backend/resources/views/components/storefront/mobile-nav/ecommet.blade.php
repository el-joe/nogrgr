{{-- Ecommet mobile nav (Figma 1:2879): floating dark pill, active item = white pill with label. --}}
@props(['items' => [], 'active' => null])
<nav class="fixed inset-x-3 bottom-3 z-40 lg:hidden" aria-label="{{ __('storefront::shell.mobile.label') }}">
    <ul class="flex items-center justify-between rounded-full bg-sf-ink px-4 py-2 text-white shadow-lg" role="list">
        @foreach ($items as $key => $item)
            <li>
                <a href="{{ route($item['route']) }}" @class(['mo-press inline-flex h-10 items-center gap-2 rounded-full px-3', 'bg-white text-sf-ink font-medium' => $active === $key]) @if ($active === $key) aria-current="page" @endif aria-label="{{ __($item['label']) }}">
                    <x-ui::icon :name="$item['icon']" :size="22" />
                    @if ($active === $key)<span class="text-sm">{{ __($item['label']) }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
<div class="h-20 lg:hidden" aria-hidden="true"></div>
