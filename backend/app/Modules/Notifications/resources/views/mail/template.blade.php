{{--
    Layout of every admin-template e-mail (EmailTemplateRenderer). $body is sanitised
    HTML with escaped variable values. Inline styles are required by e-mail clients.
--}}
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $header }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f3f1;font-family:Arial,Helvetica,sans-serif;color:#2b1a14;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;">
        <tr>
            <td style="padding:24px 28px 8px;font-size:20px;font-weight:bold;">{{ $header }}</td>
        </tr>
        <tr>
            <td style="padding:8px 28px 24px;font-size:14px;line-height:1.6;">{!! $body !!}</td>
        </tr>
        <tr>
            <td style="padding:16px 28px;border-top:1px solid #eee7e2;font-size:12px;color:#8a7a72;">{{ $platform }}</td>
        </tr>
    </table>
</body>
</html>
