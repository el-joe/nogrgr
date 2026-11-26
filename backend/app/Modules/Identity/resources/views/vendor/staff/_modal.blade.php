{{-- Remote modal shell of the staff page: skeleton, error with retry, fetched content, footer. --}}
<x-ui::modal :id="$id" :title="$title">
    <div data-remote-loading class="grid gap-4" aria-busy="true">
        <x-ui::skeleton shape="rect" class="h-10 w-full" />
        <x-ui::skeleton shape="rect" class="h-10 w-full" />
        <x-ui::skeleton shape="rect" class="h-10 w-2/3" />
    </div>
    <div data-remote-error hidden>
        <x-ui::empty-state compact variant="error" :description="__('identity::staff.load_error')" />
        <div class="mt-3 flex justify-center">
            <x-ui::button variant="secondary" icon="refresh" data-remote-retry>{{ __('identity::staff.retry') }}</x-ui::button>
        </div>
    </div>
    <div data-remote-body hidden></div>
    <x-slot:footer>
        @if ($form !== null)
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" :form="$form" data-remote-submit>{{ __('identity::staff.send_invite') }}</x-ui::button>
        @else
            <x-ui::button variant="secondary" data-overlay-close>{{ __('identity::staff.close') }}</x-ui::button>
        @endif
    </x-slot:footer>
</x-ui::modal>
