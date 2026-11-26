{{--
    One compliance document slot of a step: the uploaded file (status, view / download,
    remove while editable) and the dropzone to upload or replace it (sent with the form as
    documents[<type>]). Inputs: $type (ComplianceDocumentType), $document (?ComplianceDocument),
    $hasFile (bool), $required (bool), $canEdit (bool).
--}}
@php
    $label = $type->label();
    $locale = app()->getLocale();
@endphp
<div class="grid gap-3" data-document-slot="{{ $type->value }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-medium text-ink">{{ $label }}</h3>
        <x-ui::badge :tone="$required ? 'brand' : 'neutral'" variant="outline" :dot="false">{{ $required ? __('platform::vendor_compliance.documents.required') : __('platform::vendor_compliance.documents.optional') }}</x-ui::badge>
    </div>

    @if ($document)
        <div class="flex flex-wrap items-center gap-3 rounded-control border border-line bg-surface px-4 py-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-control bg-subtle text-ink-brand" aria-hidden="true"><x-ui::icon name="file" :size="18" /></span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink"><bdi>{{ $document->original_name ?? $label }}</bdi></p>
                <p class="text-xs text-ink-secondary">
                    @if ($hasFile)
                        {{ __('platform::vendor_compliance.documents.uploaded_on', ['date' => $document->updated_at?->locale($locale)->translatedFormat('j M Y')]) }}
                        @if ($document->size) · <bdi dir="ltr">{{ \Illuminate\Support\Number::fileSize($document->size) }}</bdi>@endif
                    @else
                        <span class="text-danger">{{ __('platform::vendor_compliance.documents.file_missing') }}</span>
                    @endif
                </p>
            </div>
            <x-ui::badge :tone="$document->status->tone()">{{ $document->status->label() }}</x-ui::badge>
            <div class="flex items-center gap-1">
                @if ($hasFile)
                    <x-ui::button size="sm" variant="ghost" icon="eye" :href="vendor_route('settings.compliance.documents.show', ['document' => $document->id])" target="_blank" rel="noopener">{{ __('platform::vendor_compliance.documents.view') }}</x-ui::button>
                    <x-ui::button size="sm" variant="ghost" icon="download" :href="vendor_route('settings.compliance.documents.show', ['document' => $document->id, 'download' => 1])">{{ __('platform::vendor_compliance.documents.download') }}</x-ui::button>
                @endif
                @if ($canEdit)
                    <x-ui::button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        data-confirm="{{ __('platform::vendor_compliance.documents.remove_message') }}"
                        data-confirm-title="{{ __('platform::vendor_compliance.documents.remove_title', ['document' => $label]) }}"
                        data-confirm-label="{{ __('platform::vendor_compliance.documents.remove') }}"
                        data-confirm-method="DELETE"
                        :data-confirm-url="vendor_route('settings.compliance.documents.destroy', ['document' => $document->id])"
                    >{{ __('platform::vendor_compliance.documents.remove') }}</x-ui::button>
                @endif
            </div>
        </div>
    @elseif (! $canEdit)
        <p class="flex items-center gap-2 rounded-control border border-dashed border-line-strong bg-subtle px-4 py-3 text-sm text-ink-secondary">
            <x-ui::icon name="file" :size="18" />
            {{ __('platform::vendor_compliance.documents.none') }}
        </p>
    @endif

    @if ($canEdit)
        <x-ui::file-upload
            :name="'documents['.$type->value.']'"
            :label="$document ? __('platform::vendor_compliance.documents.replace', ['document' => $label]) : __('platform::vendor_compliance.documents.upload', ['document' => $label])"
            accept=".pdf,.png,.jpg,.jpeg,.webp,.zip,application/pdf,image/png,image/jpeg,image/webp,application/zip"
            :hint="__('platform::vendor_compliance.documents.hint')"
        />
    @endif
</div>
