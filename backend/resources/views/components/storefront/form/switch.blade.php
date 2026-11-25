{{-- Switch (track 36×20). Sends 0 when off via a hidden input. --}}
@props(['name', 'label', 'checked' => false, 'id' => null, 'value' => '1'])
@php($id ??= 'sf-'.\App\Shared\Support\Ui::id($name))
<label for="{{ $id }}" {{ $attributes->class(['inline-flex cursor-pointer items-center gap-3 text-sm text-sf-ink']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" class="sf-switch" @checked($checked)>
    <span>{{ $label }}</span>
</label>
