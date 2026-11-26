{{-- Published / "show in footer" of a page: status pill (+ switch with store.pages.manage). --}}
@php
    $on = (bool) $page->{$flag};
    $title = (string) ($page->title ?: $page->slug);
@endphp
<div class="flex items-center gap-3">
    @if ($flag === 'is_active')
        <x-ui::status :status="$on ? 'active' : 'draft'">{{ __($on ? 'content::store_pages.status.published' : 'content::store_pages.status.draft') }}</x-ui::status>
    @endif
    @if ($canManage)
        <x-ui::switch :name="$flag.'_'.$page->id" :id="'store-page-'.$flag.'-'.$page->id" :checked="$on" :url="vendor_route('store.pages.toggle', ['page' => $page->id])" :field="$flag" :aria-label="__($flag === 'is_active' ? 'content::store_pages.publish_named' : 'content::store_pages.footer_named', ['title' => $title])" />
    @elseif ($flag === 'show_in_footer')
        <x-ui::status :status="$on ? 'active' : 'inactive'">{{ __($on ? 'content::store_pages.footer_shown' : 'content::store_pages.footer_hidden') }}</x-ui::status>
    @endif
</div>
