<div class="grid">
    <span class="font-medium text-ink">{{ trim($cost->country->flag.' '.$cost->country->name) }}</span>
    <span class="text-xs text-ink-secondary">{{ __('shipping::fixed_costs.display_in', ['currency' => $cost->country->currency_code ?? 'USD']) }}</span>
</div>
