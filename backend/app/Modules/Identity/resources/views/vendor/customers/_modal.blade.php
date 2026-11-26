{{-- Remote modal shell of the customers pages: skeleton, error with retry, fetched form, Cancel / Save. --}}
<x-ui::modal :id="$id" :title="$title">
    <div data-remote-loading class="grid gap-4" aria-busy="true">
        <x-ui::skeleton shape="rect" class="h-10 w-full" />
        <x-ui::skeleton shape="rect" class="h-10 w-full" />
        <x-ui::skeleton shape="rect" class="h-10 w-2/3" />
    </div>
    <div data-remote-error hidden>
        <x-ui::empty-state compact variant="error" :description="__('identity::customers.load_error')" />
        <div class="mt-3 flex justify-center">
            <x-ui::button variant="secondary" icon="refresh" data-remote-retry>{{ __('identity::customers.retry') }}</x-ui::button>
        </div>
    </div>
    <div data-remote-body hidden></div>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('identity::customers.cancel') }}</x-ui::button>
        <x-ui::button type="submit" :form="$form" data-remote-submit>{{ __('identity::customers.save') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
