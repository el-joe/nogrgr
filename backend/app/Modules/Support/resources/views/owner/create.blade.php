{{-- New support ticket opened by URL in the owner portal (OWN-009 without the modal). --}}
<x-layouts.panel surface="owner" :title="__('support::tickets.new_ticket_title')" data-page="owner-support">
    <div class="mx-auto grid w-full max-w-3xl gap-6">
        <x-ui::page-header :title="__('support::tickets.new_ticket_title')" :subtitle="__('support::tickets.new_ticket_subtitle')"
            :breadcrumbs="[__('navigation.owner.support') => route('owner.support'), __('support::tickets.new_ticket') => null]" />
        <x-ui::card>
            @include('support::owner._form')
            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                <x-ui::button type="submit" form="owner-ticket-form" icon="arrow-up">{{ __('support::tickets.thread.send') }}</x-ui::button>
                <x-ui::button variant="secondary" :href="route('owner.support')">{{ __('ui.cancel') }}</x-ui::button>
            </div>
        </x-ui::card>
    </div>
</x-layouts.panel>
