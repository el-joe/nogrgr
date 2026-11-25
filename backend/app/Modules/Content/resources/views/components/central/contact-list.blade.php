{{--
    Contact rows with the Figma vuesax icons (location / call / sms, orange 24px).
    contacts: iterable<array{key: string, icon?: string, value: string, href?: ?string}>.
--}}
@props(['contacts' => []])
@php
    $iconFor = fn (string $key): string => match ($key) {
        'address' => 'location', 'phone', 'whatsapp' => 'call', default => 'sms',
    };
@endphp
<ul {{ $attributes->class(['flex flex-col']) }}>
    @foreach ($contacts as $contact)
        @php $ltr = in_array($contact['key'] ?? '', ['phone', 'whatsapp', 'email'], true); @endphp
        <li class="flex items-start gap-2">
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/central/contact/'.$iconFor($contact['key'] ?? 'email').'.svg') }}" alt="" width="24" height="24" class="size-6 shrink-0">
            <span class="sr-only">{{ __('content::site.contact_keys.'.($contact['key'] ?? 'email')) }}:</span>
            @if (! empty($contact['href']))
                <a href="{{ $contact['href'] }}" class="site-link min-w-0 break-words" @if ($ltr) dir="ltr" @endif>{{ $contact['value'] }}</a>
            @else
                <span class="min-w-0 break-words" @if ($ltr) dir="ltr" @endif>{{ $contact['value'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
