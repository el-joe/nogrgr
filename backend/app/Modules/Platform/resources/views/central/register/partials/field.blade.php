{{--
    Floating-label field (Figma "Field / …" 125:11339): 56h, radius 8, 11px label over a
    15px value. Inputs: $name, $label; optional $type, $value, $required, $readonly,
    $hint, $icon, $suffix (text after the value), $dir, $autocomplete, $inputmode,
    $maxlength, $placeholder, $toggle (password show/hide), $status (availability icon),
    $extra (HtmlString after the input, e.g. a button), $id.
--}}
@php
    $type ??= 'text';
    $id ??= 'reg-'.str_replace('_', '-', $name);
    $required ??= false;
    $readonly ??= false;
    $fieldError = $errors->first($name);
    $fieldValue = $type === 'password' ? null : old($name, $value ?? null);
    $describedBy = trim((! empty($hint) ? $id.'-hint ' : '').$id.'-message');
@endphp
<div class="grid gap-1.5" data-field="{{ $name }}">
    <label for="{{ $id }}" @class([
        'reg-field flex h-14 items-center gap-2 rounded-lg border px-4',
        'bg-(--reg-readonly)' => $readonly,
        'bg-white' => ! $readonly,
        'border-(--reg-border)' => ! $fieldError,
    ]) @if ($fieldError) data-state="error" @endif>
        @if (! empty($icon))
            <x-ui::icon :name="$icon" :size="18" class="shrink-0 text-(--reg-muted)" />
        @endif
        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="text-[11px] leading-[15px] text-(--reg-muted)">{{ $label }}@if ($required) *@endif</span>
            @if (! empty($suffix))
                {{-- Value + suffix stay in reading order (megz.nogrgr.com) in RTL too. --}}
                <span class="flex min-w-0 items-baseline gap-1" dir="ltr">
                    <input
                        id="{{ $id }}"
                        name="{{ $name }}"
                        type="{{ $type }}"
                        @if ($fieldValue !== null) value="{{ $fieldValue }}" @endif
                        @if ($required) required @endif
                        @if ($readonly) readonly aria-readonly="true" @endif
                        @if (! empty($dir)) dir="{{ $dir }}" @endif
                        @if (! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
                        @if (! empty($inputmode)) inputmode="{{ $inputmode }}" @endif
                        @if (! empty($maxlength)) maxlength="{{ $maxlength }}" @endif
                        @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
                        aria-describedby="{{ $describedBy }}"
                        @if ($fieldError) aria-invalid="true" @endif
                        class="w-full min-w-0 bg-transparent p-0 text-[15px] leading-[21px] text-ink outline-none placeholder:text-ink-muted read-only:cursor-default"
                    >
                    <span class="shrink-0 text-[15px] leading-[21px] text-(--reg-muted)">{{ $suffix }}</span>
                </span>
            @else
                <input
                    id="{{ $id }}"
                    name="{{ $name }}"
                    type="{{ $type }}"
                    @if ($fieldValue !== null) value="{{ $fieldValue }}" @endif
                    @if ($required) required @endif
                    @if ($readonly) readonly aria-readonly="true" @endif
                    @if (! empty($dir)) dir="{{ $dir }}" @endif
                    @if (! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
                    @if (! empty($inputmode)) inputmode="{{ $inputmode }}" @endif
                    @if (! empty($maxlength)) maxlength="{{ $maxlength }}" @endif
                    @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
                    aria-describedby="{{ $describedBy }}"
                    @if ($fieldError) aria-invalid="true" @endif
                    class="w-full min-w-0 bg-transparent p-0 text-[15px] leading-[21px] text-ink outline-none placeholder:text-ink-muted read-only:cursor-default"
                >
            @endif
        </span>
        @if (! empty($status))
            <span class="grid size-5 shrink-0 place-items-center" data-status-icon aria-hidden="true"></span>
        @endif
        @if (! empty($toggle))
            <button type="button" class="grid size-8 shrink-0 place-items-center rounded-md text-(--reg-muted) transition-colors hover:text-ink" data-password-toggle="{{ $id }}" aria-pressed="false" aria-label="{{ __('platform::register.account.show_password') }}" data-label-show="{{ __('platform::register.account.show_password') }}" data-label-hide="{{ __('platform::register.account.hide_password') }}">
                <x-ui::icon name="eye" :size="18" />
            </button>
        @endif
        {{ $extra ?? '' }}
    </label>
    @if (! empty($hint))
        <p id="{{ $id }}-hint" class="text-[13px] leading-[18px] text-(--reg-muted)">{{ $hint }}</p>
    @endif
    <p id="{{ $id }}-message" class="reg-status text-[13px] leading-[18px] text-danger" data-message-for="{{ $name }}" role="status" aria-live="polite" @if (! $fieldError) hidden @endif>{{ $fieldError }}</p>
</div>
