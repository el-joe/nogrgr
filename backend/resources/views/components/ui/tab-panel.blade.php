{{-- Panel for <x-ui::tabs id="…">: <x-ui::tab-panel tabs="settings" key="general" :active="true">. --}}
@props(['tabs', 'key', 'active' => false])
<div role="tabpanel" id="{{ $tabs }}-{{ $key }}" aria-labelledby="{{ $tabs }}-{{ $key }}-tab" tabindex="0" @unless ($active) hidden @endunless {{ $attributes->class(['outline-none']) }}>
    {{ $slot }}
</div>
