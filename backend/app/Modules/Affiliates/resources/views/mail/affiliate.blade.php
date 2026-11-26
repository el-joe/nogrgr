{{--
    MAIL-038 / 039 / 040 affiliate e-mails (Figma Emails & printables 131:11159 · 131:11207
    · 131:11262): preheader, brand top bar, logo + label, status icon, heading, detail
    rows, optional note (warning box), call to action and the footer. Built-in layout
    used when the admin has no active template of the event. Inline styles: required by
    e-mail clients.
    $locale, $platform, $name, $url, $layout (tone, icon, label, heading, rows, note, cta).
--}}
@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $initial = mb_strtoupper(mb_substr($platform, 0, 1));
    $success = $layout['tone'] === 'success';
    $iconBg = $success ? '#e5f5ec' : '#fdeceb';
    $iconFg = $success ? '#16a34a' : '#dc2626';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $layout['heading'] }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f3eeea;font-family:Outfit,'Noto Sans Arabic',Arial,Helvetica,sans-serif;color:#2b1a14;">
    <div style="display:none;max-height:0;overflow:hidden;">{{ $layout['heading'] }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border-top:6px solid #ff5a2c;">
        <tr>
            <td style="padding:28px 32px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="text-align:{{ $align }};">
                            <span style="display:inline-block;width:36px;height:36px;line-height:36px;border-radius:8px;background:#ff5a2c;color:#ffffff;font-weight:700;font-size:18px;text-align:center;vertical-align:middle;">{{ $initial }}</span>
                            <span style="display:inline-block;margin-inline-start:10px;font-size:18px;font-weight:700;vertical-align:middle;">{{ $platform }}</span>
                        </td>
                        <td style="text-align:{{ $opposite }};font-size:13px;color:#7a6a62;white-space:nowrap;">{{ $layout['label'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 32px 0;text-align:center;">
                <span style="display:inline-block;width:64px;height:64px;line-height:64px;border-radius:50%;background:{{ $iconBg }};color:{{ $iconFg }};font-size:28px;font-weight:700;">{{ $success ? '✓' : '!' }}</span>
                <h1 style="margin:16px 0 0;font-size:24px;line-height:32px;font-weight:700;color:#2b1a14;">{{ $layout['heading'] }}</h1>
                @if ($name !== '')
                    <p style="margin:8px 0 0;font-size:14px;color:#7a6a62;">{{ __('affiliates::notifications.mail.greeting', ['name' => $name]) }}</p>
                @endif
            </td>
        </tr>
        @if ($layout['rows'] !== [])
            <tr>
                <td style="padding:20px 32px 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee7e2;border-radius:10px;border-collapse:separate;overflow:hidden;">
                        @foreach ($layout['rows'] as $row)
                            <tr style="background:{{ $loop->odd ? '#f8f5f2' : '#ffffff' }};">
                                <td style="padding:12px 16px;font-size:14px;color:#7a6a62;text-align:{{ $align }};">{{ $row['label'] }}</td>
                                <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#2b1a14;text-align:{{ $opposite }};">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endif
        @if (filled($layout['note']))
            <tr>
                <td style="padding:16px 32px 0;">
                    <div style="background:#fdf1e3;border-radius:10px;padding:14px 16px;font-size:14px;line-height:22px;color:#2b1a14;text-align:{{ $align }};">{{ $layout['note'] }}</div>
                </td>
            </tr>
        @endif
        @if (filled($url) && filled($layout['cta']))
            <tr>
                <td style="padding:24px 32px 0;text-align:center;">
                    <a href="{{ $url }}" style="display:inline-block;background:#ff5a2c;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 28px;border-radius:10px;">{{ $layout['cta'] }}</a>
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:28px 32px 0;"></td>
        </tr>
        <tr>
            <td style="padding:20px 32px 28px;background:#f8f5f2;border-top:1px solid #eee7e2;text-align:center;font-size:12px;line-height:20px;color:#7a6a62;">
                {{ __('affiliates::notifications.mail.footer', ['platform' => $platform]) }}
            </td>
        </tr>
    </table>
</body>
</html>
