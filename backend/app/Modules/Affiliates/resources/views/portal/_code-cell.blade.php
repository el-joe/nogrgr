{{-- Promo code cell of AFF-010: code (LTR) + copy button. $code (AffiliatePromoCode). --}}
<span class="inline-flex items-center gap-2">
    <span class="font-semibold text-ink" dir="ltr">{{ $code->code }}</span>
    <x-ui::icon-button icon="copy" size="sm" :label="__('affiliates::panel.referral.copy')" :data-copy-text="$code->code" />
</span>
