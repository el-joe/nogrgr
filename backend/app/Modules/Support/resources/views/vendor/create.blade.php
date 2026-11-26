{{-- New support ticket opened by URL (VEN-091a without the modal): the same form in a page card. --}}
<x-vendor::layout :title="__('support::tickets.new_ticket_title')" page="vendor-support">
    <div class="mx-auto grid w-full max-w-3xl gap-6">
        <x-ui::page-header :title="__('support::tickets.new_ticket_title')" :subtitle="__('support::tickets.new_ticket_subtitle')"
            :breadcrumbs="[__('support::tickets.vendor_title') => vendor_route('support.index'), __('support::tickets.new_ticket') => null]" />
        <x-ui::card>
            @include('support::vendor._form')
            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                <x-ui::button type="submit" form="support-ticket-form" icon="arrow-up">{{ __('support::tickets.thread.send') }}</x-ui::button>
                <x-ui::button variant="secondary" :href="vendor_route('support.index')">{{ __('ui.cancel') }}</x-ui::button>
            </div>
        </x-ui::card>
    </div>
</x-vendor::layout>
