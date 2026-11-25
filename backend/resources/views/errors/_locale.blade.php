{{-- Error pages may render outside the web middleware (unmatched routes): apply ?lang= / the locale cookie like SetLocale. --}}
@php
    $requestedLocale = request()->query('lang');
    $requestedLocale = is_string($requestedLocale) && \App\Shared\Support\Locale::isSupported($requestedLocale) ? $requestedLocale : request()->cookie(\App\Shared\Http\Middleware\SetLocale::COOKIE);

    if (is_string($requestedLocale) && \App\Shared\Support\Locale::isSupported($requestedLocale)) {
        app()->setLocale($requestedLocale);
    }
@endphp
