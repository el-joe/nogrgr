{{--
    Maintenance window modal (ADM-113): create (POST), edit (PUT, open windows) and view
    (finished windows / viewers: fields disabled, no save). [data-maint-new] resets it,
    [data-maint-edit='{…}'] fills it. The store picker (Select2, remote search) shows
    for scope "one store"; the start field hides when "Start now" is on.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('system.maintenance.manage');
    $scopes = \App\Modules\Platform\Enums\MaintenanceScope::options();
@endphp
<x-ui::modal id="maintenance-modal" :title="$canManage ? __('platform::maintenance.create_title') : __('platform::maintenance.details.title')" :subtitle="__('platform::maintenance.modal_subtitle', ['tz' => config('app.timezone')])" size="md">
    <form
        id="maintenance-form"
        method="POST"
        action="{{ route('admin.system.maintenance.store') }}"
        @if ($canManage) data-ajax @endif
        data-maint-form
        data-store-url="{{ route('admin.system.maintenance.store') }}"
        data-title-create="{{ __('platform::maintenance.create_title') }}"
        data-title-edit="{{ __('platform::maintenance.edit_title') }}"
        data-title-view="{{ __('platform::maintenance.details.title') }}"
        class="grid gap-5 sm:grid-cols-2"
        novalidate
    >
        @csrf
        <input type="hidden" name="_method" value="POST" data-maint-method>
        <x-ui::input name="title" :label="__('platform::maintenance.fields.title')" maxlength="120" required class="sm:col-span-2" data-autofocus />
        <x-ui::select name="scope" :label="__('platform::maintenance.fields.scope')" :options="$scopes" selected="platform" :search="false" required />
        <div data-maint-store hidden>
            @if ($canManage)
                <x-ui::select name="tenant_id" :label="__('platform::maintenance.fields.store')" :ajax-url="route('admin.system.maintenance.stores')" :placeholder="__('platform::maintenance.fields.store_placeholder')" required />
            @else
                <x-ui::input name="tenant_label" :label="__('platform::maintenance.fields.store')" disabled />
            @endif
        </div>
        <x-ui::input name="notes" :label="__('platform::maintenance.fields.notes')" :helper="__('platform::maintenance.fields.notes_helper')" maxlength="255" class="sm:col-span-2" />
        <div class="sm:col-span-2">
            <x-ui::switch name="starts_now" :label="__('platform::maintenance.fields.starts_now')" data-maint-now />
        </div>
        <div data-maint-start>
            <x-ui::input name="starts_at" type="datetime-local" :label="__('platform::maintenance.fields.starts_at')" required dir="ltr" />
        </div>
        <x-ui::input name="ends_at" type="datetime-local" :label="__('platform::maintenance.fields.ends_at')" :helper="__('platform::maintenance.fields.ends_at_helper')" dir="ltr" />
        <x-ui::textarea name="message_en" :label="__('platform::maintenance.fields.message_en')" maxlength="500" rows="3" dir="ltr" class="sm:col-span-2" />
        <x-ui::textarea name="message_ar" :label="__('platform::maintenance.fields.message_ar')" :helper="__('platform::maintenance.fields.message_helper')" maxlength="500" rows="3" dir="rtl" class="sm:col-span-2" />
        <x-ui::textarea name="allowed_ips" :label="__('platform::maintenance.fields.allowed_ips')" :helper="__('platform::maintenance.fields.allowed_ips_helper')" rows="3" dir="ltr" class="sm:col-span-2" spellcheck="false" />
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        @if ($canManage)
            <x-ui::button type="submit" form="maintenance-form" icon="check" data-maint-save>{{ __('platform::maintenance.save') }}</x-ui::button>
        @endif
    </x-slot:footer>
</x-ui::modal>
