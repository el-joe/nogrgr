{{-- Text / email / password input. type="password" adds the show-password eye. --}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'id' => null, 'required' => false, 'helper' => null, 'error' => null, 'icon' => null, 'useOld' => true, 'serverError' => true])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= 'sf-'.\App\Shared\Support\Ui::id($name);
    $error ??= $serverError ? \App\Shared\Support\Ui::error($errorKey, $errors ?? null) : null;
    $current = $type === 'password' ? null : ($useOld ? \App\Shared\Support\Ui::old($errorKey, $value) : $value);
@endphp
<x-sf::form.field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div class="sf-control">
        @if ($icon)<x-ui::icon :name="$icon" :size="18" class="text-sf-muted" />@endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($current !== null) value="{{ $current }}" @endif aria-describedby="{{ $helper ? $id.'-helper ' : '' }}{{ $id }}-error" @if ($error) aria-invalid="true" @endif @required($required) {{ $attributes->except('class') }}>
        @if ($type === 'password')
            <button type="button" data-password-toggle="#{{ $id }}" aria-pressed="false" class="grid size-8 place-items-center text-sf-muted hover:text-sf-ink" aria-label="{{ __st('shell.show_password') }}" title="{{ __st('shell.show_password') }}"><x-ui::icon name="eye" :size="18" /></button>
        @endif
    </div>
</x-sf::form.field>
