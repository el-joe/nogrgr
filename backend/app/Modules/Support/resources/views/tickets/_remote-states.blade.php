{{-- Body of the new-ticket remote modal (resources/js/panels/remote-modal.js): skeleton, error with retry, fetched form. --}}
<div data-remote-loading class="grid gap-4" aria-busy="true">
    <x-ui::skeleton shape="rect" class="h-10 w-full" />
    <x-ui::skeleton shape="rect" class="h-10 w-full" />
    <x-ui::skeleton shape="rect" class="h-24 w-full" />
</div>
<div data-remote-error hidden>
    <x-ui::empty-state compact variant="error" :description="__('support::tickets.errors.form_load')" />
    <div class="mt-3 flex justify-center">
        <x-ui::button variant="secondary" icon="refresh" data-remote-retry>{{ __('support::tickets.retry') }}</x-ui::button>
    </div>
</div>
<div data-remote-body hidden></div>
