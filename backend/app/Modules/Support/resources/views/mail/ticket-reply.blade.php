{{--
    MAIL-037 Support ticket reply (Figma Emails & printables 131:11125): preheader, brand
    top bar, logo + "Ticket #", heading, quoted reply with the author, "Reply to ticket"
    button and the footer. Built-in layout used when the admin has no active
    `support.ticket_reply` template. Inline styles: required by e-mail clients.
    $locale, $platform, $variables (EmailTemplateEvent::SupportTicketReply variables).
--}}
@php
    $rtl = $locale === 'ar';
    $align = $rtl ? 'right' : 'left';
    $reference = $variables['ticket.reference'] ?? '';
    $initial = mb_strtoupper(mb_substr($platform, 0, 1));
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('support::tickets.mail.heading', ['reference' => $reference]) }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f3eeea;font-family:Outfit,'Noto Sans Arabic',Arial,Helvetica,sans-serif;color:#2b1a14;">
    <div style="display:none;max-height:0;overflow:hidden;">{{ __('support::tickets.mail.preheader', ['reference' => $reference]) }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border-top:6px solid #ff5a2c;">
        <tr>
            <td style="padding:28px 32px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="text-align:{{ $align }};">
                            <span style="display:inline-block;width:36px;height:36px;line-height:36px;border-radius:8px;background:#ff5a2c;color:#ffffff;font-weight:700;font-size:18px;text-align:center;vertical-align:middle;">{{ $initial }}</span>
                            <span style="display:inline-block;margin-inline-start:10px;font-size:18px;font-weight:700;vertical-align:middle;">{{ $platform }}</span>
                        </td>
                        <td style="text-align:{{ $rtl ? 'left' : 'right' }};font-size:13px;color:#7a6a62;white-space:nowrap;">{{ __('support::tickets.mail.ticket', ['reference' => $reference]) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 32px 8px;text-align:{{ $align }};">
                <h1 style="margin:0;font-size:24px;line-height:32px;font-weight:700;color:#2b1a14;">{{ __('support::tickets.mail.heading', ['reference' => $reference]) }}</h1>
                @if (filled($variables['ticket.subject'] ?? null))
                    <p style="margin:6px 0 0;font-size:13px;color:#7a6a62;">{{ __('support::tickets.mail.subject_line', ['subject' => $variables['ticket.subject']]) }}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:16px 32px;">
                <div style="background:#f8f5f2;border-radius:10px;padding:14px 16px;border-{{ $rtl ? 'right' : 'left' }}:3px solid #ff5a2c;font-size:15px;line-height:24px;color:#2b1a14;text-align:{{ $align }};">
                    @if (filled($variables['recipient.name'] ?? null))
                        {{ __('support::tickets.mail.greeting', ['name' => $variables['recipient.name']]) }}
                    @endif
                    {{ $variables['reply.excerpt'] ?? '' }}
                    {{ __('support::tickets.mail.signature', ['author' => $variables['reply.author'] ?? '']) }}
                </div>
            </td>
        </tr>
        @if (filled($variables['ticket.url'] ?? null))
            <tr>
                <td style="padding:8px 32px 32px;text-align:{{ $align }};">
                    <a href="{{ $variables['ticket.url'] }}" style="display:inline-block;background:#ff5a2c;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:14px 28px;border-radius:10px;">{{ __('support::tickets.mail.cta') }}</a>
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:20px 32px 28px;background:#f8f5f2;border-top:1px solid #eee7e2;text-align:center;font-size:12px;line-height:20px;color:#7a6a62;">
                {{ __('support::tickets.mail.footer', ['platform' => $platform]) }}
            </td>
        </tr>
    </table>
</body>
</html>
