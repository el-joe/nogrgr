{{-- Status modal (list rows and detail header): select of the kind's statuses. --}}
<x-ui::modal id="request-status-modal" :title="__('catalog::requests.status_modal.title')" :subtitle="__('catalog::requests.status_modal.subtitle')" size="sm">
    <form id="request-status-form" method="POST" action="{{ $action ?? '#' }}" data-request-status-form novalidate class="grid gap-4">
        @csrf
        <x-ui::select name="status" :label="__('catalog::requests.status_modal.status')" :options="$statuses" :selected="$current ?? null" :search="false" required />
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="request-status-form" icon="check">{{ __('catalog::requests.status_modal.save') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
