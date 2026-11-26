{{-- Social link create / edit (VEN-034f), loaded into #appearance-modal: network (one link each), profile address, visible. --}}
<form
    id="appearance-link-form"
    method="POST"
    action="{{ $link->exists ? vendor_route('store.appearance.social.update', ['link' => $link->id]) : vendor_route('store.appearance.social.store') }}"
    data-ajax
    novalidate
    class="grid gap-5"
    data-modal-title="{{ $title }}"
    data-submit-label="{{ __('content::store_appearance.save') }}"
>
    @csrf
    @if ($link->exists)
        @method('PUT')
    @endif
    <x-ui::select name="network" :label="__('content::store_appearance.social.network')" :options="$networks" :selected="$link->network?->value" required />
    <x-ui::input name="url" type="url" :label="__('content::store_appearance.social.url')" :value="$link->url" maxlength="500" dir="ltr" :placeholder="$link->network?->placeholder()" :helper="__('content::store_appearance.social.url_help')" required />
    <x-ui::switch name="is_active" :label="__('content::store_appearance.social.active')" :checked="(bool) $link->is_active" />
</form>
