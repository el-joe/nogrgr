{{--
    Full-page frame for a remote-modal fragment opened by URL (RendersRemoteModals):
    same panel shell as the list it belongs to, a card with the fragment and, for a
    form, Cancel / submit. [data-success-url] sends the page back after a save.
    The vendor surface uses the client Tenant Panel shell <x-vendor::layout> like every
    vendor list page (decision #101, RQA-VEN-003); admin / affiliate keep <x-layouts.panel>.
--}}
@php
    /** @var \App\Shared\Http\RemoteModalPage $page */
    $breadcrumbs = $page->breadcrumbs !== [] ? $page->breadcrumbs : [$page->title => null];
@endphp
@if ($page->surface === 'vendor')
    <x-vendor::layout :title="$page->title" :page="$page->pageModule">
        @include('panels._remote-modal-page-body')
    </x-vendor::layout>
@else
    <x-layouts.panel :surface="$page->surface" :title="$page->title" :data-page="$page->pageModule">
        @include('panels._remote-modal-page-body')
    </x-layouts.panel>
@endif
