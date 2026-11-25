<span class="grid min-w-0">
    <span class="font-medium text-ink">{{ $currency->name }}</span>
    <span class="text-xs text-ink-secondary"><bdi dir="ltr">{{ $currency->code }}</bdi>@if ($currency->isBase()) · {{ __('localization::admin.currencies.base') }}@endif</span>
</span>
