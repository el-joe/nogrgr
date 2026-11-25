{{-- History row action. @param \App\Modules\Catalog\Models\CatalogImportRun $run --}}
<div class="flex justify-end">
    <x-ui::button variant="ghost" size="sm" icon="list" :href="route('admin.catalog.imports.index', ['driver' => $run->driver->value, 'run' => $run->id])">{{ __('catalog::imports.history.view') }}</x-ui::button>
</div>
