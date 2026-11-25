{{--
    File upload (Figma 28:10325): dashed dropzone (drag over = brand border + tint),
    then one row per file — uploading (4px progress), uploaded (size · Uploaded, delete)
    or failed (error + retry). With upload-url files upload immediately and the returned
    id is submitted as <name> (or <name>[] when multiple); otherwise the files are sent
    with the form.
--}}
@props(['name', 'label' => null, 'accept' => null, 'multiple' => false, 'hint' => null, 'uploadUrl' => null, 'id' => null, 'required' => false, 'disabled' => false, 'error' => null])
@php
    $errorKey = \App\Shared\Support\Ui::errorKey($name);
    $id ??= \App\Shared\Support\Ui::id($name);
    $error ??= \App\Shared\Support\Ui::error($errorKey, $errors ?? null);
    $valueName = $multiple && ! str_ends_with($name, '[]') ? $name.'[]' : $name;
@endphp
<x-ui::field :label="$label" :for="$id" :required="$required" :error="$error" :error-key="$errorKey" {{ $attributes->only('class') }}>
    <div data-upload @if ($uploadUrl) data-upload-url="{{ $uploadUrl }}" data-upload-name="{{ $valueName }}" @endif class="grid gap-2">
        <label
            for="{{ $id }}"
            data-dropzone
            @class([
                'group flex flex-col items-center gap-1 rounded-card border-[1.5px] border-dashed bg-subtle p-6 text-center transition-colors',
                'data-dragover:border-primary data-dragover:bg-primary-subtle',
                'border-danger' => $error,
                'border-line-strong' => ! $error,
                'cursor-not-allowed opacity-70' => $disabled,
                'cursor-pointer hover:border-primary' => ! $disabled,
            ])
        >
            <span class="mb-2 grid size-10 place-items-center rounded-full bg-surface text-ink-brand shadow-card">
                <x-ui::icon name="upload" :size="20" />
            </span>
            <span class="text-sm text-ink-secondary">
                <span class="font-medium text-ink-brand underline-offset-2 group-hover:underline">{{ __('ui.upload.click') }}</span>
                {{ __('ui.upload.or_drag') }}
            </span>
            @if ($hint)
                <span class="text-xs text-ink-muted">{{ $hint }}</span>
            @endif
            <input
                type="file"
                id="{{ $id }}"
                class="sr-only"
                @unless ($uploadUrl) name="{{ $valueName }}" @endunless
                @if ($accept) accept="{{ $accept }}" @endif
                @if ($multiple) multiple @endif
                @if ($error) aria-invalid="true" @endif
                aria-describedby="{{ $id }}-error"
                @required($required && ! $uploadUrl)
                @disabled($disabled)
                {{ $attributes->except('class') }}
            >
        </label>
        <ul data-upload-list class="grid gap-2" aria-live="polite"></ul>
        <template data-upload-row>
            <li class="group/row flex items-center gap-3 rounded-control border border-line bg-surface px-4 py-3 data-[state=error]:border-danger">
                <x-ui::icon name="file" :size="20" class="text-ink-brand group-data-[state=error]/row:text-danger" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] font-medium text-ink" data-file-name></p>
                    <div class="mt-1.5 hidden h-1 overflow-hidden rounded-full bg-muted group-data-[state=uploading]/row:block">
                        <div class="h-full w-0 rounded-full bg-primary transition-[width]" data-file-progress></div>
                    </div>
                    <p class="text-xs text-ink-secondary group-data-[state=done]/row:text-success group-data-[state=error]/row:text-danger group-data-[state=uploading]/row:hidden" data-file-meta></p>
                </div>
                <button type="button" data-file-retry class="hidden size-8 place-items-center rounded-chip text-ink-secondary group-data-[state=error]/row:grid hover:bg-subtle" aria-label="{{ __('ui.upload.retry') }}" title="{{ __('ui.upload.retry') }}">
                    <x-ui::icon name="refresh" :size="18" />
                </button>
                <button type="button" data-file-remove class="grid size-8 place-items-center rounded-chip text-ink-secondary hover:bg-subtle hover:text-danger" aria-label="{{ __('ui.upload.remove') }}" title="{{ __('ui.upload.remove') }}">
                    <x-ui::icon name="trash" :size="18" />
                </button>
            </li>
        </template>
    </div>
</x-ui::field>
