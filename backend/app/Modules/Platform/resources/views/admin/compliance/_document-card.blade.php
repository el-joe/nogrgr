{{--
    Compliance review › one document: status pill, preview (image → <img>, PDF → embedded
    viewer, other types → open / download, missing file → empty state; always streamed
    through the authorized admin route), then number (masked), holder, expiry (expired
    warning) and "matches store owner". Managers can flag it verified / needs action.
--}}
@use('App\Modules\Platform\Enums\ComplianceDocumentStatus')
@php
    $url = route('admin.vendors.compliance.document', ['complianceProfile' => $profile->id, 'document' => $document->id]);
    $downloadUrl = route('admin.vendors.compliance.document', ['complianceProfile' => $profile->id, 'document' => $document->id, 'download' => 1]);
    $statusUrl = route('admin.vendors.compliance.document.status', ['complianceProfile' => $profile->id, 'document' => $document->id]);
    $label = $document->type->label();
    $number = $document->maskedNumber();
@endphp
<x-ui::card :title="$label" :id="'document-'.$document->id">
    <x-slot:actions>
        <x-ui::badge :tone="$document->status->tone()">{{ $document->status->label() }}</x-ui::badge>
    </x-slot:actions>

    @if (! $hasFile)
        <div class="rounded-control border border-dashed border-line-strong bg-subtle">
            <x-ui::empty-state compact icon="file" :title="__('platform::compliance.document.missing_title')" :description="__('platform::compliance.document.missing_description')" />
        </div>
    @elseif ($document->isImage())
        <a href="{{ $url }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-control border border-line bg-subtle">
            <img src="{{ $url }}" alt="{{ __('platform::compliance.document.preview_alt', ['document' => $label]) }}" loading="lazy" class="mx-auto max-h-96 w-full object-contain">
        </a>
    @elseif ($document->isPdf())
        <iframe src="{{ $url }}" title="{{ __('platform::compliance.document.preview_alt', ['document' => $label]) }}" class="h-[28rem] w-full rounded-control border border-line bg-subtle" loading="lazy"></iframe>
    @else
        <div class="flex items-center gap-3 rounded-control border border-line bg-subtle px-4 py-3">
            <x-ui::icon name="file" :size="24" class="shrink-0 text-ink-secondary" />
            <div class="grid min-w-0 flex-1">
                <span class="truncate font-medium text-ink" dir="ltr">{{ $document->original_name ?? basename((string) $document->path) }}</span>
                <span class="text-xs text-ink-secondary">{{ __('platform::compliance.document.no_preview') }}</span>
            </div>
        </div>
    @endif

    @if ($hasFile)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <x-ui::button size="sm" variant="ghost" icon="eye" :href="$url" target="_blank" rel="noopener">{{ __('platform::compliance.document.open') }}</x-ui::button>
            <x-ui::button size="sm" variant="ghost" icon="download" :href="$downloadUrl">{{ __('platform::compliance.document.download') }}</x-ui::button>
        </div>
    @endif

    <dl class="mt-4 grid gap-3 text-sm">
        <div class="flex items-start justify-between gap-4">
            <dt class="text-ink-secondary">{{ __('platform::compliance.document.number') }}</dt>
            <dd class="text-end font-medium text-ink"><bdi dir="ltr">{{ $number ?? '—' }}</bdi></dd>
        </div>
        <div class="flex items-start justify-between gap-4">
            <dt class="text-ink-secondary">{{ __('platform::compliance.document.holder') }}</dt>
            <dd class="text-end font-medium text-ink">{{ $document->holder_name ?? '—' }}</dd>
        </div>
        <div class="flex items-start justify-between gap-4">
            <dt class="text-ink-secondary">{{ __('platform::compliance.document.expires') }}</dt>
            <dd class="flex flex-wrap items-center justify-end gap-2 text-end font-medium text-ink">
                @if ($document->expires_at)
                    {{ $document->expires_at->locale(app()->getLocale())->translatedFormat('j M Y') }}
                    @if ($document->isExpired())
                        <x-ui::badge tone="danger">{{ __('platform::compliance.document.expired') }}</x-ui::badge>
                    @endif
                @else
                    {{ __('platform::compliance.document.no_expiry') }}
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between gap-4">
            <dt class="text-ink-secondary">{{ __('platform::compliance.document.matches_owner') }}</dt>
            <dd>
                @if ($document->matches_owner === true)
                    <x-ui::badge tone="success">{{ __('platform::compliance.document.yes') }}</x-ui::badge>
                @elseif ($document->matches_owner === false)
                    <x-ui::badge tone="danger">{{ __('platform::compliance.document.no') }}</x-ui::badge>
                @else
                    <x-ui::badge tone="neutral">{{ __('platform::compliance.document.not_checked') }}</x-ui::badge>
                @endif
            </dd>
        </div>
    </dl>

    @if ($canManage)
        <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-line pt-4">
            @if ($document->status !== ComplianceDocumentStatus::NeedsAction)
                <x-ui::button size="sm" variant="secondary" icon="alert-triangle" data-review-request data-method="PATCH" :data-url="$statusUrl" data-status="needs_action">{{ __('platform::compliance.document.flag_needs_action') }}</x-ui::button>
            @endif
            @if ($document->status !== ComplianceDocumentStatus::Verified)
                <x-ui::button size="sm" variant="secondary" icon="check" data-review-request data-method="PATCH" :data-url="$statusUrl" data-status="verified">{{ __('platform::compliance.document.flag_verified') }}</x-ui::button>
            @endif
        </div>
    @endif
</x-ui::card>
