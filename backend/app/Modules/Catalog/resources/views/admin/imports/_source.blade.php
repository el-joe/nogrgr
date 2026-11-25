{{--
    Source card (Figma 65:22120): driver, endpoint, last full sync, category mapping and
    the run controls (pause / resume / cancel, catalog.imports.manage).
    @param ImportDriver $driver, ?string $endpoint, ?CatalogImportRun $lastSync, ?CatalogImportRun $run,
           ?array $progress, int $mapped, int $mappings, bool $canManage, bool $canIntegrations
--}}
@php
    $locale = app()->getLocale();
    $rows = [
        __('catalog::imports.source.driver') => $driver->label(),
        __('catalog::imports.source.endpoint') => $endpoint ?? '—',
        __('catalog::imports.source.last_sync') => $lastSync?->finished_at?->locale($locale)->translatedFormat('j M Y') ?? __('catalog::imports.source.never'),
        __('catalog::imports.source.mapping') => __('catalog::imports.source.mapping_value', ['mapped' => number_format($mapped), 'total' => number_format($mappings)]),
    ];
@endphp
<x-ui::card :title="__('catalog::imports.source.title')">
    <dl class="grid gap-3 text-sm">
        @foreach ($rows as $label => $value)
            <div class="flex items-center justify-between gap-3">
                <dt class="text-ink-secondary">{{ $label }}</dt>
                <dd class="truncate text-end font-medium text-ink"><bdi>{{ $value }}</bdi></dd>
            </div>
        @endforeach
    </dl>

    @if ($canManage && $run !== null && $progress !== null)
        <div class="mt-5 flex flex-wrap items-center gap-3" data-run-controls>
            <x-ui::button variant="secondary" icon="clock" data-import-action="pause" data-url="{{ route('admin.catalog.imports.pause', $run) }}" :hidden="! $progress['can_pause']">{{ __('catalog::imports.source.pause') }}</x-ui::button>
            <x-ui::button variant="secondary" icon="refresh" data-import-action="resume" data-url="{{ route('admin.catalog.imports.resume', $run) }}" :hidden="! $progress['can_resume']">{{ __('catalog::imports.source.resume') }}</x-ui::button>
            <x-ui::button variant="ghost" icon="x" class="text-danger" data-import-action="cancel" data-url="{{ route('admin.catalog.imports.cancel', $run) }}"
                data-confirm-title="{{ __('catalog::imports.source.cancel_title') }}" data-confirm-message="{{ __('catalog::imports.source.cancel_message') }}" data-confirm-label="{{ __('catalog::imports.source.cancel_confirm') }}"
                :hidden="! $progress['can_cancel']">{{ __('catalog::imports.source.cancel') }}</x-ui::button>
        </div>
    @endif

    @if ($canIntegrations)
        <div class="mt-5 border-t border-line pt-4">
            <a href="{{ route('admin.integrations.show', $driver->integrationSection()) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-brand hover:underline">
                <x-ui::icon name="settings" :size="16" /> {{ __('catalog::imports.source.settings') }}
            </a>
        </div>
    @endif
</x-ui::card>
