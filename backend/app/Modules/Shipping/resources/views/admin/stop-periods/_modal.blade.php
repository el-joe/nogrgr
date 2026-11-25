<x-ui::modal id="stop-period-modal" :title="__('shipping::stop_periods.create_title')" size="md">
    @include('shipping::admin.partials.remote-states')
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('shipping::common.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="stop-period-form" icon="check" data-remote-submit>{{ __('shipping::common.save') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
