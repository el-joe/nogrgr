{{--
    Body of a shipping modal whose content is fetched (resources/js/panels/remote-modal.js):
    skeleton while loading, error state with retry, then the fetched markup.
--}}
<div data-remote-loading class="grid gap-4" aria-busy="true">
    <x-ui::skeleton shape="rect" class="h-10 w-full" />
    <x-ui::skeleton shape="rect" class="h-10 w-full" />
    <x-ui::skeleton shape="rect" class="h-10 w-2/3" />
</div>
<div data-remote-error hidden>
    <x-ui::empty-state compact variant="error" :description="__('shipping::common.load_error')" />
    <div class="mt-3 flex justify-center">
        <x-ui::button variant="secondary" icon="refresh" data-remote-retry>{{ __('shipping::common.retry') }}</x-ui::button>
    </div>
</div>
<div data-remote-body hidden></div>
