{{--
    Text input (Figma Input 27:10219): 44h, radius 10, leading icon, suffix slot.
    States: default / focus (1.5px brand + ring) / error / disabled. type="password"
    adds a show/hide toggle. Error comes from :error, the $errors bag or a 422 (JS).
    <x-ui::input name="name" :label="__('…')" required helper="…" icon="mail" />
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    'icon' => null,
    'helper' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'labelHidden' => false,
])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $describedBy = trim(($helper ? $id.'-helper ' : '').$id.'-error');
    $current = $type === 'password' ? null : \App\Shared\Support\Ui::old($errorKey, $value);
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" :label-hidden="$labelHidden" {{ $attributes->only('class') }}>
    <div class="ui-control">
        @if ($icon)
            <x-ui::icon :name="$icon" :size="18" class="text-ink-muted" />
        @endif
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($current !== null) value="{{ $current }}" @endif
            aria-describedby="{{ $describedBy }}"
            @if ($error) aria-invalid="true" @endif
            @required($required)
            @disabled($disabled)
            {{ $attributes->except('class') }}
            @if ($type === 'password') dir="ltr" @endif
        >
        {{ $suffix ?? '' }}
        @if ($type === 'password')
            <button type="button" class="grid size-7 place-items-center rounded-chip text-ink-muted hover:bg-subtle hover:text-ink" data-password-toggle="#{{ $id }}" aria-pressed="false" aria-label="{{ __('ui.show_password') }}" title="{{ __('ui.show_password') }}">
                <x-ui::icon name="eye" :size="18" />
            </button>
        @endif
    </div>
</x-ui::field>
