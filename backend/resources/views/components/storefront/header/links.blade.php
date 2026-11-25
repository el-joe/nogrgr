{{-- Header navigation links filtered to registered routes: [['route' => …, 'label' => …, 'icon' => …]]. --}}
@props(['links' => [], 'tone' => 'light'])
@foreach ($links as $link)
    @if (Route::has($link['route']))
        <a href="{{ route($link['route']) }}" @class(['inline-flex items-center gap-1 whitespace-nowrap text-base hover:underline', 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark'])>
            @isset($link['icon'])<x-ui::icon :name="$link['icon']" :size="20" />@endisset{{ __($link['label']) }}
        </a>
    @endif
@endforeach
