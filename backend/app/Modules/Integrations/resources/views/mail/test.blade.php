{{-- Test e-mail of Admin › Integrations › Mail (IntegrationTestMail). --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><title>{{ __('integrations::admin.mail.test_subject', ['name' => $platformName]) }}</title></head>
<body>
    <h1>{{ __('integrations::admin.mail.test_heading') }}</h1>
    <p>{{ __('integrations::admin.mail.test_body', ['name' => $platformName, 'time' => now()->toDateTimeString()]) }}</p>
</body>
</html>
