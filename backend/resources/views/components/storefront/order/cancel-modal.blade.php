{{--
    Cancel order, 2 steps (SF-ECO-028c, Figma Storefront additions 103:107273, m sheets
    103:107488). One modal per page; an opener carries
    data-cancel-order='{"url": store-api cancel URL, "number": …, "paid": formatted amount|null}'
    (storefront/core/account/orders.js). Step 2 posts {reason} as JSON.
--}}
<x-sf::modal id="sf-cancel-order" :title="__st('orders.cancel_title')" size="sm" data-cancel-modal>
    <div class="grid gap-4" data-cancel-step="1">
        <p class="text-xs text-sf-muted">{{ __st('orders.cancel_step_one') }}</p>
        <fieldset class="grid gap-3">
            <legend class="sr-only">{{ __st('orders.cancel_step_one') }}</legend>
            @foreach (['ordered_by_mistake', 'found_better_price', 'delivery_too_long', 'changed_mind', 'other'] as $reason)
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-sf-ink">
                    <input type="radio" name="cancel_reason" value="{{ __st('orders.reasons.'.$reason) }}" class="sf-radio" @checked($loop->first)>
                    {{ __st('orders.reasons.'.$reason) }}
                </label>
            @endforeach
        </fieldset>
        <p class="sf-error" data-cancel-reason-error hidden>{{ __st('orders.choose_reason') }}</p>
        <button type="button" class="sf-btn sf-btn-cta w-full" data-cancel-next>{{ __st('orders.continue') }}</button>
    </div>

    <form method="POST" class="grid gap-4" data-ajax data-cancel-form data-cancel-step="2" hidden novalidate>
        <input type="hidden" name="reason" value="">
        <p class="text-xs text-sf-muted">{{ __st('orders.cancel_step_two') }}</p>
        <div class="flex items-start gap-2.5 rounded-lg bg-sf-warning-bg p-3 text-sm text-sf-ink">
            <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-sf-warning" />
            <p><span data-cancel-summary></span> <span data-cancel-refund hidden></span></p>
        </div>
        <x-sf::form.field :label="__st('orders.note_label')" for="sf-cancel-note">
            <textarea id="sf-cancel-note" class="sf-control text-sm min-h-20 py-2.5" maxlength="400" placeholder="{{ __st('orders.note_placeholder') }}" data-cancel-note></textarea>
        </x-sf::form.field>
        <p class="sf-error" data-error-for="reason" hidden></p>
        <div class="grid grid-cols-2 gap-3">
            <button type="button" class="sf-btn sf-btn-outline" data-sheet-close>{{ __st('orders.keep_order') }}</button>
            <x-sf::form.button type="submit" variant="accent" :busy="__st('orders.cancelling')">{{ __st('orders.cancel') }}</x-sf::form.button>
        </div>
    </form>

    <template data-i18n-cancel>
        <span data-text="summary">{{ __st('orders.cancel_summary', ['number' => ':number']) }}</span>
        <span data-text="refund">{{ __st('orders.refund_note', ['amount' => ':amount']) }}</span>
    </template>
</x-sf::modal>
