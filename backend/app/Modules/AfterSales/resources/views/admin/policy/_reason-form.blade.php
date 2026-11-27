{{-- ADM-090 reason modal: English / Arabic label, video required, seller fault, active, order. --}}
<form id="return-reason-form" method="POST" action="{{ $action }}" data-ajax novalidate class="grid gap-4" data-modal-title="{{ $title }}">
    @csrf
    @if ($reason->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::input name="name[en]" :label="__('after-sales::policy.reasons.name_en')" :value="$reason->exists ? $reason->getTranslation('name', 'en', false) : ''" maxlength="80" required dir="ltr" />
        <x-ui::input name="name[ar]" :label="__('after-sales::policy.reasons.name_ar')" :value="$reason->exists ? $reason->getTranslation('name', 'ar', false) : ''" maxlength="80" required dir="rtl" />
    </div>
    <x-ui::input name="sort_order" type="number" min="0" max="1000" :label="__('after-sales::policy.reasons.sort')" :value="$reason->sort_order" />
    <x-ui::switch name="requires_video" :label="__('after-sales::policy.reasons.video_required')" :checked="(bool) $reason->requires_video" />
    <x-ui::switch name="seller_fault" :label="__('after-sales::policy.reasons.seller_fault')" :description="__('after-sales::policy.reasons.seller_fault_help')" :checked="(bool) $reason->seller_fault" />
    <x-ui::switch name="is_active" :label="__('after-sales::policy.reasons.active')" :checked="(bool) $reason->is_active" />
    @include('commerce::partials.modal-actions', ['submit' => __('after-sales::common.save')])
</form>
