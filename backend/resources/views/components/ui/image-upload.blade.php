{{--
    Image upload (Figma 28:10357): 96px tiles, radius 10. The first tile is "Main"
    (inverse badge); drag tiles to reorder; hover shows delete. Existing images:
    :images="[['id' => 1, 'url' => '…']]" are kept via <name>_keep[] in display order.
--}}
@props(['name', 'label' => null, 'images' => [], 'max' => 0, 'accept' => 'image/*', 'id' => null, 'required' => false, 'helper' => null, 'error' => null])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $base = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :helper="$helper" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div data-image-upload data-max="{{ (int) $max }}">
        <div class="flex flex-wrap gap-3" data-image-grid>
            @foreach ($images as $index => $image)
                <div data-image-tile class="group/tile relative size-24 cursor-grab overflow-hidden rounded-control bg-subtle">
                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? '' }}" class="size-full object-contain bg-media" loading="lazy">
                    <input type="hidden" name="{{ $base }}_keep[]" value="{{ $image['id'] }}">
                    <span data-main-badge class="absolute bottom-1.5 start-1.5 rounded-chip bg-inverse px-1.5 text-xs font-medium text-on-inverse" @if ($index !== 0) hidden @endif>{{ __('ui.upload.main') }}</span>
                    <button type="button" data-image-remove class="absolute end-1.5 top-1.5 grid size-7 place-items-center rounded-chip bg-surface/90 text-ink opacity-0 shadow-card transition-opacity group-hover/tile:opacity-100 hover:text-danger focus-visible:opacity-100" aria-label="{{ __('ui.upload.remove_image') }}" title="{{ __('ui.upload.remove_image') }}">
                        <x-ui::icon name="trash" :size="16" />
                    </button>
                </div>
            @endforeach
            <label for="{{ $id }}" data-image-add class="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-control border-[1.5px] border-dashed border-line-strong bg-subtle text-xs font-medium text-ink-brand transition-colors hover:border-primary hover:bg-primary-subtle" @if ($max > 0 && count($images) >= $max) hidden @endif>
                <x-ui::icon name="image" :size="20" />
                {{ __('ui.upload.add_image') }}
                <input type="file" id="{{ $id }}" name="{{ $base }}[]" accept="{{ $accept }}" multiple class="sr-only" @if ($error) aria-invalid="true" @endif aria-describedby="{{ $id }}-helper {{ $id }}-error" {{ $attributes->except('class') }}>
            </label>
        </div>
        <template data-image-tile-template>
            <div data-image-tile class="group/tile relative size-24 cursor-grab overflow-hidden rounded-control bg-subtle">
                <img src="" alt="" class="size-full object-contain bg-media">
                <span data-main-badge class="absolute bottom-1.5 start-1.5 rounded-chip bg-inverse px-1.5 text-xs font-medium text-on-inverse" hidden>{{ __('ui.upload.main') }}</span>
                <button type="button" data-image-remove class="absolute end-1.5 top-1.5 grid size-7 place-items-center rounded-chip bg-surface/90 text-ink opacity-0 shadow-card transition-opacity group-hover/tile:opacity-100 hover:text-danger focus-visible:opacity-100" aria-label="{{ __('ui.upload.remove_image') }}" title="{{ __('ui.upload.remove_image') }}">
                    <x-ui::icon name="trash" :size="16" />
                </button>
            </div>
        </template>
    </div>
</x-ui::field>
