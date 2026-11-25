{{-- History "Run" cell. @param \App\Modules\Catalog\Models\CatalogImportRun $run, string $locale --}}
<div class="grid min-w-0 gap-0.5">
    <a href="{{ route('admin.catalog.imports.index', ['driver' => $run->driver->value, 'run' => $run->id]) }}" class="font-medium text-ink hover:text-ink-brand">{{ __('catalog::imports.history.run_label', ['id' => $run->id, 'driver' => $run->driver->label()]) }}</a>
    <span class="text-xs text-ink-secondary">
        {{ $run->created_at?->locale($locale)->translatedFormat('j M Y, H:i') }}
        @if ($run->starter)
            · {{ __('catalog::imports.history.by', ['name' => $run->starter->name]) }}
        @endif
    </span>
</div>
