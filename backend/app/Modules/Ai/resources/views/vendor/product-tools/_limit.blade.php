{{-- "Limit reached" state of an AI tool modal (HTTP 429): message + upgrade link. --}}
<div class="grid gap-2 rounded-card bg-warning-bg p-4" role="alert" hidden data-ai-limit>
    <p class="text-sm font-semibold text-ink">{{ __('ai::tools.quota.reached_title') }}</p>
    <p class="text-sm text-ink-secondary" data-ai-limit-text></p>
    @if (Route::has('vendor.finance.subscription') && auth('vendor')->user()?->can('finance.subscription.view'))
        <a href="{{ vendor_route('finance.subscription') }}" class="text-sm font-medium text-ink-brand hover:underline">{{ __('ai::tools.quota.upgrade') }}</a>
    @endif
</div>
