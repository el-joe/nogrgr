{{--
    Support request + newsletter (SF-ECO-035, Figma Storefront additions 114:108109 /
    m 114:108353): form (name, email, phone with intl-tel-input, order number, subject,
    message with counter, photos), "Other ways to reach us" card, newsletter signup.
    Both forms post JSON (data-ajax): success toast, inline 422 errors.
    Data: $intro (optional sanitised HTML shown above the form).
--}}
@php
    $customer = auth('customer')->user();
    $email = tenancy()->initialized ? tenant('owner_email') : null;
    $phone = tenancy()->initialized ? tenant('owner_phone') : null;
    $max = \App\Modules\Engagement\Http\Requests\Storefront\StoreSupportRequest::MESSAGE_MAX;
@endphp
@if (filled($intro ?? null))
    <div class="mb-6 max-w-3xl text-sm leading-5 text-sf-muted [&_a]:text-sf-accent [&_a]:underline [&_strong]:text-sf-ink">{!! $intro !!}</div>
@endif
<div class="grid gap-6">
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <form method="POST" action="{{ route('storefront.support.store') }}" enctype="multipart/form-data" data-ajax data-reset-on-success novalidate
              class="grid gap-4 rounded-sf-card border border-sf-border bg-sf-surface p-4 md:p-8" data-reveal
              x-data="{ count: 0, files: 0 }">
            @csrf
            <x-sf::alert hidden data-form-error>{{ __st('account.fix_fields') }}</x-sf::alert>
            <div class="grid gap-4 md:grid-cols-2">
                <x-sf::form.input name="name" :label="__st('support.fields.name')" :value="$customer?->name" :placeholder="__st('support.placeholders.name')" autocomplete="name" maxlength="120" required />
                <x-sf::form.input name="email" type="email" :label="__st('support.fields.email')" :value="$customer?->email" :placeholder="__st('support.placeholders.email')" autocomplete="email" maxlength="160" dir="ltr" required />
                <x-sf::form.phone name="phone" :label="__st('support.fields.phone')" :value="$customer?->phone" :country="$customer?->phone_country" />
                <x-sf::form.input name="order_number" :label="__st('support.fields.order_number')" :placeholder="__st('support.placeholders.order_number')" maxlength="32" dir="ltr" />
            </div>
            <x-sf::form.field :label="__st('support.fields.subject')" for="sf-support-subject" required error-key="subject">
                <div class="sf-control relative">
                    <select id="sf-support-subject" name="subject" required aria-describedby="sf-support-subject-error" class="appearance-none pe-8">
                        <option value="">{{ __st('support.placeholders.subject') }}</option>
                        @foreach (\App\Modules\Engagement\Enums\SupportSubject::storefrontOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-ui::icon name="chevron-down" :size="16" class="pointer-events-none absolute end-3 text-sf-muted" />
                </div>
            </x-sf::form.field>
            <x-sf::form.field :label="__st('support.fields.message')" for="sf-support-message" required error-key="message">
                <textarea id="sf-support-message" name="message" rows="6" maxlength="{{ $max }}" required class="sf-control min-h-[140px] resize-y py-3" placeholder="{{ __st('support.placeholders.message') }}" aria-describedby="sf-support-message-count sf-support-message-error" x-on:input="count = $event.target.value.length"></textarea>
                <p id="sf-support-message-count" class="text-xs leading-4 text-sf-muted" dir="ltr" aria-live="polite"><span x-text="count">0</span> / {{ $max }}</p>
            </x-sf::form.field>
            <x-sf::form.field error-key="attachments">
                <label class="flex cursor-pointer items-center justify-center gap-2.5 rounded-sf-input border border-dashed border-sf-border px-4 py-[18px] text-sm leading-5 text-sf-muted transition-colors hover:border-sf-accent hover:text-sf-ink focus-within:border-sf-accent">
                    <x-ui::icon name="upload" :size="18" class="text-sf-accent" />
                    <span x-show="files === 0">{{ __st('support.attach') }}</span>
                    <span x-show="files > 0" x-cloak data-template="{{ __st('support.attached', ['count' => '__N__']) }}" x-text="$el.dataset.template.replace('__N__', files)"></span>
                    <input type="file" name="attachments[]" accept="image/jpeg,image/png" multiple class="sr-only" x-on:change="files = $event.target.files.length">
                </label>
            </x-sf::form.field>
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <div>
                <x-sf::form.button type="submit" size="lg" :busy="__st('support.sending')">{{ __st('support.send') }}</x-sf::form.button>
            </div>
        </form>

        <aside class="flex flex-col gap-3.5 rounded-sf-card bg-sf-bg p-6" data-reveal>
            <h2 class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('support.other_ways') }}</h2>
            @if (filled($email))
                <a href="mailto:{{ $email }}" class="flex items-center gap-2.5 text-sm leading-5 text-sf-ink hover:text-sf-accent" dir="ltr"><x-ui::icon name="mail" :size="18" class="text-sf-accent" />{{ $email }}</a>
            @endif
            @if (filled($phone))
                <a href="tel:{{ $phone }}" class="flex items-center gap-2.5 text-sm leading-5 text-sf-ink hover:text-sf-accent"><x-ui::icon name="message" :size="18" class="text-sf-accent" /><span>{{ __st('support.call_us', ['phone' => $phone]) }}</span></a>
            @endif
            <a href="{{ route('storefront.pages.show', \App\Modules\Storefront\Enums\SystemStorePage::Faq->value) }}" class="flex items-center gap-2.5 text-sm leading-5 text-sf-ink hover:text-sf-accent"><x-ui::icon name="lifebuoy" :size="18" class="text-sf-accent" />{{ __st('support.browse_faqs') }}</a>
        </aside>
    </div>

    @include('storefront::themes.ecommet.partials.newsletter-signup')
</div>
