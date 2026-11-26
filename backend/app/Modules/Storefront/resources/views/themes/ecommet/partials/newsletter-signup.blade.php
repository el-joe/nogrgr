{{-- Newsletter signup block (SF-ECO-035, Figma 114:108274): title, intro, email + Subscribe (JSON, toast). --}}
<form method="POST" action="{{ route('storefront.newsletter.store') }}" data-ajax data-reset-on-success novalidate class="flex flex-col gap-3 rounded-sf-card bg-sf-accent-subtle p-5 md:p-8" data-reveal>
    @csrf
    <h2 class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('support.newsletter.title') }}</h2>
    <p class="text-sm leading-5 text-sf-muted">{{ __st('support.newsletter.intro') }}</p>
    <div class="flex flex-col gap-3 md:flex-row md:items-start">
        <x-sf::form.input name="email" type="email" :label="__st('support.fields.email')" :placeholder="__st('support.newsletter.placeholder')" autocomplete="email" maxlength="160" dir="ltr" required class="flex-1 [&_label]:sr-only" :server-error="false" />
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
        <x-sf::form.button type="submit" :busy="__st('support.sending')" class="md:h-11">{{ __st('support.newsletter.button') }}</x-sf::form.button>
    </div>
</form>
