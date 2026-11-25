{{-- One weight band row: name, min g, max g (empty = no limit), price USD. --}}
<tr data-band>
    <td class="px-3 py-2 align-top">
        <label class="sr-only" for="band-{{ $z }}-{{ $b }}-name">{{ __('shipping::rules.fields.band_name') }}</label>
        <input id="band-{{ $z }}-{{ $b }}-name" type="text" name="zones[{{ $z }}][bands][{{ $b }}][name]" value="{{ $band?->name }}" maxlength="120" required placeholder="{{ __('shipping::rules.fields.band_placeholder') }}" class="ui-control h-9 w-full min-w-28 px-2">
    </td>
    <td class="px-3 py-2 align-top">
        <label class="sr-only" for="band-{{ $z }}-{{ $b }}-min">{{ __('shipping::rules.fields.min_weight') }}</label>
        <input id="band-{{ $z }}-{{ $b }}-min" type="number" min="0" step="1" name="zones[{{ $z }}][bands][{{ $b }}][min_weight_grams]" value="{{ $band?->min_weight_grams ?? 0 }}" required dir="ltr" class="ui-control h-9 w-24 px-2 text-end">
    </td>
    <td class="px-3 py-2 align-top">
        <label class="sr-only" for="band-{{ $z }}-{{ $b }}-max">{{ __('shipping::rules.fields.max_weight') }}</label>
        <input id="band-{{ $z }}-{{ $b }}-max" type="number" min="0" step="1" name="zones[{{ $z }}][bands][{{ $b }}][max_weight_grams]" value="{{ $band?->max_weight_grams }}" placeholder="∞" dir="ltr" class="ui-control h-9 w-24 px-2 text-end">
    </td>
    <td class="px-3 py-2 align-top">
        <label class="sr-only" for="band-{{ $z }}-{{ $b }}-price">{{ __('shipping::rules.fields.price') }}</label>
        <input id="band-{{ $z }}-{{ $b }}-price" type="number" min="0" step="0.01" name="zones[{{ $z }}][bands][{{ $b }}][price]" value="{{ $band?->price->toDecimal() }}" required placeholder="0.00" dir="ltr" class="ui-control h-9 w-24 px-2 text-end">
    </td>
    <td class="px-2 py-2 align-top">
        <x-ui::icon-button icon="trash" size="sm" :label="__('shipping::rules.remove_band')" data-band-remove />
    </td>
</tr>
