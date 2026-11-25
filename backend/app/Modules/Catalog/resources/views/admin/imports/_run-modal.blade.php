{{--
    Run import modal: driver + options, the live "Preview" of the source's first page
    (loaded when the modal opens, with loading / error + retry states) and Start.
    @param ImportDriver $driver, array<string, string> $drivers
--}}
<x-ui::modal id="run-import-modal" :title="__('catalog::imports.modal.title')" :subtitle="__('catalog::imports.modal.subtitle')" size="lg">
    <form id="run-import-form" method="POST" action="{{ route('admin.catalog.imports.store') }}" class="grid gap-5" data-import-form data-preview-url="{{ route('admin.catalog.imports.preview') }}" novalidate>
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui::select name="driver" :label="__('catalog::imports.modal.driver')" :options="$drivers" :selected="$driver->value" :search="false" required data-import-driver />
            <x-ui::input name="max_pages" type="number" min="1" max="10000" inputmode="numeric" :label="__('catalog::imports.modal.max_pages')" :helper="__('catalog::imports.modal.max_pages_help')" />
        </div>
        <x-ui::switch name="update_existing" :label="__('catalog::imports.modal.update_existing')" :description="__('catalog::imports.modal.update_existing_help')" checked />

        <section class="grid gap-3 border-t border-line pt-4" aria-labelledby="import-preview-title">
            <h3 id="import-preview-title" class="text-sm font-semibold text-ink">{{ __('catalog::imports.modal.preview_title') }}</h3>
            <div data-preview-loading class="grid gap-2" aria-busy="true" hidden>
                <x-ui::skeleton shape="rect" class="h-8 w-1/2" />
                <x-ui::skeleton shape="rect" class="h-10 w-full" />
                <x-ui::skeleton shape="rect" class="h-10 w-full" />
            </div>
            <div data-preview-error hidden>
                <x-ui::empty-state compact variant="error" :title="__('catalog::imports.modal.preview_error')" description="">
                    <x-ui::button variant="secondary" icon="refresh" data-preview-retry>{{ __('catalog::imports.modal.retry') }}</x-ui::button>
                </x-ui::empty-state>
                <p class="text-center text-[13px] text-danger" data-preview-error-message></p>
            </div>
            <div data-preview-body hidden></div>
        </section>
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::imports.modal.close') }}</x-ui::button>
        <x-ui::button type="submit" form="run-import-form" icon="download">{{ __('catalog::imports.modal.submit') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
