{{--
    Contact (Figma 12:41842, mobile 12:43632): heading + contact rows beside the form
    (fields 55px, radius 8, label inside with red asterisk; textarea 145px; full-width
    orange submit). Ajax form → inline success; 422 field errors; 429 toast.
    Data: $seo, $channels {contacts, socials}.
--}}
@php
    $contacts = collect($channels['contacts'] ?? []);
    $socials = collect($channels['socials'] ?? [])->filter(fn (array $social): bool => filled($social['url'] ?? null));
    $iconFor = fn (string $key): string => match ($key) { 'address' => 'location', 'phone', 'whatsapp' => 'call', default => 'sms' };
    $field = 'site-input w-full rounded-lg border border-[#d9d9d9] bg-white px-4 pb-2 text-sm text-site-ink placeholder:text-transparent';
@endphp
<x-central::layout :seo="$seo" page="site-contact">
    <section class="relative mx-auto grid max-w-[1180px] gap-8 px-4 pt-6 pb-12 lg:grid-cols-[1fr_470px] lg:items-center lg:gap-16 lg:px-[90px] lg:pt-[89px] lg:pb-[84px]">
        <span class="site-glow site-glow--page lg:hidden" aria-hidden="true"></span>
        <div class="relative flex flex-col gap-6 text-center lg:gap-10 lg:text-start" data-reveal="fade-start">
            <div class="flex flex-col gap-2 lg:gap-4">
                <h1 class="text-xl font-medium text-site-ink lg:text-[32px]">{{ __('content::site.contact.title') }}</h1>
                <p class="text-xs text-site-gray lg:max-w-[430px] lg:text-xl">{{ __('content::site.contact.text') }}</p>
            </div>
            @if ($contacts->isNotEmpty())
                <ul class="flex flex-col items-center gap-6 lg:items-start lg:gap-4">
                    @foreach ($contacts as $contact)
                        @php $ltr = in_array($contact['key'] ?? '', ['phone', 'whatsapp', 'email'], true); @endphp
                        <li class="flex flex-col items-center gap-2 text-xs text-site-ink lg:flex-row lg:gap-2 lg:text-xl">
                            <span class="grid size-8 place-items-center rounded-full bg-site-tint lg:size-auto lg:bg-transparent">
                                <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/central/contact/'.$iconFor($contact['key'] ?? 'email').'.svg') }}" alt="" width="24" height="24" class="size-4 lg:size-6">
                            </span>
                            <span class="sr-only">{{ __('content::site.contact_keys.'.($contact['key'] ?? 'email')) }}:</span>
                            @if (! empty($contact['href']))
                                <a href="{{ $contact['href'] }}" class="site-link" @if ($ltr) dir="ltr" @endif>{{ $contact['value'] }}</a>
                            @else
                                <span @if ($ltr) dir="ltr" @endif>{{ $contact['value'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($socials->isNotEmpty())
                <div class="flex flex-col items-center gap-3 lg:items-start">
                    <p class="text-base font-medium">{{ __('content::site.contact.follow') }}</p>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($socials as $social)
                            <li><a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="site-social block text-primary" aria-label="{{ __('content::site.networks.'.$social['network']) }}"><x-central::social-icon :network="$social['network']" /></a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="relative rounded-2xl bg-white max-lg:p-4 max-lg:shadow-[0_8px_40px_rgba(65,37,30,0.08)]" data-ajax-state data-reveal="fade-up" data-reveal-delay="120">
            @if (Route::has('central.contact.store'))
                <form data-ajax data-inline-success method="POST" action="{{ route('central.contact.store') }}" class="flex flex-col gap-4" novalidate>
                    @csrf
                    <x-central::honeypot />
                    <div class="site-float" data-field>
                        <input id="contact-name" name="name" type="text" required autocomplete="name" placeholder=" " class="{{ $field }} h-[55px]">
                        <label for="contact-name">{{ __('content::site.contact.name') }}<span class="text-danger">*</span></label>
                    </div>
                    <div class="site-float" data-field>
                        <input id="contact-email" name="email" type="email" required autocomplete="email" placeholder=" " class="{{ $field }} h-[55px]" dir="ltr">
                        <label for="contact-email">{{ __('content::site.contact.email') }}<span class="text-danger">*</span></label>
                    </div>
                    <x-ui::phone name="phone" :label="__('content::site.contact.phone')" class="site-phone" />
                    <div class="site-float" data-field>
                        <input id="contact-subject" name="subject" type="text" required placeholder=" " class="{{ $field }} h-[55px]">
                        <label for="contact-subject">{{ __('content::site.contact.subject') }}<span class="text-danger">*</span></label>
                    </div>
                    <div class="site-float" data-field>
                        <textarea id="contact-message" name="message" required rows="5" placeholder=" " class="{{ $field }} h-[145px] resize-y pt-7"></textarea>
                        <label for="contact-message">{{ __('content::site.contact.message') }}<span class="text-danger">*</span></label>
                    </div>
                    <button type="submit" class="site-btn h-[55px] rounded-xl bg-primary text-base font-medium text-white hover:bg-primary-hover">{{ __('content::site.contact.submit') }}</button>
                </form>
                <div class="site-success flex-col py-10 text-center" hidden data-success role="status">
                    <span class="grid size-16 place-items-center rounded-full bg-success-bg text-success"><x-ui::icon name="check-circle" :size="32" /></span>
                    <p class="text-xl font-medium text-site-ink">{{ __('content::site.contact.success_title') }}</p>
                    <p class="max-w-sm text-base text-site-gray" data-success-message>{{ __('content::site.contact.success') }}</p>
                    <button type="button" class="site-btn mt-2 h-11 rounded-xl border border-primary px-6 text-primary hover:bg-site-tint" data-success-reset>{{ __('content::site.contact.send_another') }}</button>
                </div>
            @endif
        </div>
    </section>
</x-central::layout>
