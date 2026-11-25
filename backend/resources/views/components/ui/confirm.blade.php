{{--
    Confirm dialog (Figma 32:10454), rendered once by the panel layout (#ui-confirm) and
    driven by resources/js/panels/ui/confirm.js (data-confirm="…" or confirmDialog()).
    400 wide, 48px tone disc, H2, message, full-width Cancel + Danger. Focus starts on
    Cancel; irreversible actions can require typing the item's name.
--}}
<div id="ui-confirm" class="ui-overlay group/confirm" data-overlay data-sheet data-tone="danger" role="alertdialog" aria-modal="true" aria-labelledby="ui-confirm-title" aria-describedby="ui-confirm-message" aria-hidden="true" inert>
    <div class="ui-overlay-scrim"></div>
    <div class="ui-overlay-panel max-w-[400px] p-6">
        <div class="ui-sheet-grabber mx-auto -mt-4 mb-4 h-1 w-10 rounded-full bg-line-strong" data-sheet-handle aria-hidden="true"></div>
        <span class="mb-4 grid size-12 place-items-center rounded-full bg-danger-bg text-danger group-data-[tone=brand]/confirm:bg-primary-subtle group-data-[tone=brand]/confirm:text-ink-brand">
            <x-ui::icon name="trash" :size="22" class="group-data-[tone=brand]/confirm:hidden" />
            <x-ui::icon name="alert-triangle" :size="22" class="hidden group-data-[tone=brand]/confirm:block" />
        </span>
        <h2 id="ui-confirm-title" class="text-xl font-semibold leading-7 text-ink" data-confirm-title>{{ __('ui.confirm_title') }}</h2>
        <p id="ui-confirm-message" class="mt-2 text-sm text-ink-secondary" data-confirm-message></p>
        <div class="mt-4 grid gap-1.5" data-confirm-require hidden>
            <label for="ui-confirm-require" class="text-sm text-ink-secondary">
                {{ __('ui.confirm_type') }} <strong class="font-medium text-ink" data-confirm-require-name></strong>
            </label>
            <input id="ui-confirm-require" type="text" class="ui-control" autocomplete="off">
        </div>
        <div class="mt-6 grid grid-cols-2 gap-3">
            <x-ui::button variant="secondary" data-confirm-cancel data-overlay-close block>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button variant="danger" data-confirm-accept block>{{ __('ui.confirm') }}</x-ui::button>
        </div>
    </div>
</div>
