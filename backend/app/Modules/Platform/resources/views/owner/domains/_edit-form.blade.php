{{-- Edit domain modal body (OWN-004b, Figma 122:103838), loaded by the remote modal. --}}
<div data-modal-title="{{ __('platform::owner.domains.edit_title') }}" data-modal-subtitle="{{ __('platform::owner.domains.edit_subtitle', ['store' => $request->tenant?->name]) }}">
    <form id="domain-edit-form" method="POST" action="{{ route('owner.domains.update', $request->id) }}" data-ajax class="grid gap-5" novalidate>
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <x-ui::input name="host" :label="__('platform::owner.domains.domain')" :value="$request->host" required maxlength="253" dir="ltr" autocomplete="off" spellcheck="false" :helper="__('platform::owner.domains.host_hint')" data-autofocus />
        <p class="flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-xs text-ink">
            <x-ui::icon name="info" :size="16" class="shrink-0 text-info" />{{ __('platform::owner.domains.edit_info') }}
        </p>
    </form>
</div>
