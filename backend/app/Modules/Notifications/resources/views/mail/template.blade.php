{{--
    MAIL-001 shared branded layout of every e-mail (EmailTemplateRenderer, decision #118),
    after Figma Emails & printables 131:11159: preheader, brand top bar, logo (or brand
    initial) + audience label, body card, footer with social links, footer text, address
    and help / privacy links. $body is sanitised HTML with escaped variable values, already
    inline-styled (MailBodyStyler); $layout is MailLayoutData (central or store variant).
    Table layout, inline styles, 600px with a fluid phone fallback, RTL for Arabic,
    color-scheme meta + prefers-color-scheme rules for dark-mode clients.
--}}
@php
    /** @var \App\Modules\Notifications\DTOs\MailLayoutData $layout */
    $rtl = $locale === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $opposite = $rtl ? 'left' : 'right';
    $initial = mb_strtoupper(mb_substr($layout->brandName, 0, 1));
    $font = "Outfit,'Noto Sans Arabic',Arial,Helvetica,sans-serif";
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $subject }}</title>
    <style>
        @media (max-width: 620px) { .mail-card { width: 100% !important; border-radius: 0 !important; } .mail-pad { padding-left: 20px !important; padding-right: 20px !important; } }
        @media (prefers-color-scheme: dark) {
            .mail-bg { background: #1c1512 !important; }
            .mail-card { background: #2a211d !important; }
            .mail-foot { background: #231b18 !important; }
            .mail-text, .mail-text p, .mail-text td, .mail-text h2, .mail-text h3, .mail-brand { color: #f3ece8 !important; }
            .mail-text tr { background: transparent !important; }
            .mail-muted, .mail-muted p { color: #c9b9b1 !important; }
            .mail-text blockquote { background: #3a2d26 !important; color: #f3ece8 !important; }
        }
    </style>
</head>
<body class="mail-bg" style="margin:0;padding:0;background:{{ $layout->backgroundColor }};-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;" aria-hidden="true">{{ $preheader }}&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;</div>
    <table role="presentation" class="mail-bg" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $layout->backgroundColor }};" dir="{{ $dir }}">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" class="mail-card" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;border-top:6px solid {{ $layout->primaryColor }};">
                    <tr>
                        <td class="mail-pad" style="padding:28px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="text-align:{{ $align }};">
                                        @if ($layout->homeUrl)<a href="{{ $layout->homeUrl }}" style="text-decoration:none;">@endif
                                        @if ($layout->logoUrl)
                                            <img src="{{ $layout->logoUrl }}" alt="{{ $layout->brandName }}" height="36" style="height:36px;width:auto;max-width:200px;border:0;vertical-align:middle;">
                                        @else
                                            <span style="display:inline-block;width:36px;height:36px;line-height:36px;border-radius:8px;background:{{ $layout->primaryColor }};color:#ffffff;font-family:{{ $font }};font-weight:700;font-size:18px;text-align:center;vertical-align:middle;">{{ $initial }}</span>
                                            <span class="mail-brand" style="display:inline-block;margin-inline-start:10px;font-family:{{ $font }};font-size:18px;font-weight:700;color:#2b1a14;vertical-align:middle;">{{ $layout->brandName }}</span>
                                        @endif
                                        @if ($layout->homeUrl)</a>@endif
                                    </td>
                                    @if ($layout->label !== '')
                                        <td class="mail-muted" style="text-align:{{ $opposite }};font-family:{{ $font }};font-size:13px;color:#7a6a62;white-space:nowrap;">{{ $layout->label }}</td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="mail-pad mail-text" dir="{{ $dir }}" style="padding:28px 32px 12px;font-family:{{ $font }};font-size:15px;line-height:24px;color:#2b1a14;text-align:{{ $align }};">{!! $body !!}</td>
                    </tr>
                    <tr>
                        <td class="mail-pad mail-foot mail-muted" align="center" style="padding:24px 32px 28px;background:#faf7f5;border-top:1px solid #eee7e2;font-family:{{ $font }};font-size:12px;line-height:20px;color:#7a6a62;text-align:center;">
                            @if ($layout->socials !== [])
                                <p style="margin:0 0 10px;">
                                    @foreach ($layout->socials as $social)
                                        <a href="{{ $social['url'] }}" style="color:#7a6a62;text-decoration:underline;">{{ $social['label'] }}</a>@if (! $loop->last) &nbsp;·&nbsp; @endif
                                    @endforeach
                                </p>
                            @endif
                            <p style="margin:0 0 6px;">{{ $layout->footer }}</p>
                            @if ($layout->address !== '')
                                <p style="margin:0 0 6px;">{{ $layout->address }}</p>
                            @endif
                            @if ($layout->links !== [])
                                <p style="margin:0;">
                                    @foreach ($layout->links as $link)
                                        <a href="{{ $link['url'] }}" style="color:{{ $layout->primaryColor }};font-weight:600;text-decoration:none;">{{ $link['label'] }}</a>@if (! $loop->last) · @endif
                                    @endforeach
                                </p>
                            @endif
                            @if ($layout->poweredBy !== '')
                                <p style="margin:8px 0 0;">{{ $layout->poweredBy }}</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
