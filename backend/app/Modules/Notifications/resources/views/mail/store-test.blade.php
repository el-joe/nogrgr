{{-- Test e-mail of Vendor › Settings › Mail (VEN-081). Inline styles are required by e-mail clients. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Shared\Support\Locale::direction() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('notifications::vendor_mail.test_subject', ['store' => $storeName]) }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f3f1;font-family:Arial,Helvetica,sans-serif;color:#2b1a14;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;">
        <tr><td style="padding:24px 28px 8px;font-size:20px;font-weight:bold;">{{ $storeName }}</td></tr>
        <tr><td style="padding:8px 28px 24px;font-size:14px;line-height:1.6;">{{ __('notifications::vendor_mail.test_body', ['store' => $storeName]) }}</td></tr>
    </table>
</body>
</html>
