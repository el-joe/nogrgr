<?php

namespace App\Modules\Notifications\Support;

/**
 * Turns a rendered (sanitised, variables filled) template body into e-mail-client-safe
 * HTML: links whose URL came out empty or unsafe are dropped (so an optional button
 * disappears), a paragraph holding only a link becomes a bulletproof brand button, and
 * the editor's tags get inline styles (e-mail clients ignore <style> blocks).
 */
final class MailBodyStyler
{
    public static function style(string $html, string $primary, bool $rtl = false, bool $centered = true): string
    {
        $start = $rtl ? 'right' : 'left';
        $end = $rtl ? 'left' : 'right';
        $onPrimary = self::onColor($primary);

        // Drop links without a usable URL, then the blocks they leave empty.
        $html = (string) preg_replace_callback('#<a\s+href="([^"]*)"([^>]*)>(.*?)</a>#is', static function (array $match): string {
            $url = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);

            return preg_match('#^(https?://|mailto:)#i', $url) === 1 ? $match[0] : '';
        }, $html);
        $html = (string) preg_replace('#<(p|blockquote|h2|h3|li)>(\s|&nbsp;|<br\s*/?>)*</\1>#i', '', $html);

        // Figma Emails & printables 131:11159: full-width brand button, radius 10.
        // Central e-mails (Figma 129:10162): full-width button; store e-mails (129:10215): inline.
        $button = static fn (array $match): string => '<table role="presentation"'.($centered ? ' width="100%"' : '').' cellpadding="0" cellspacing="0" style="margin:8px 0 20px;"><tr><td align="center" style="border-radius:10px;background:'.$primary.';">'
            // style before href: the generic link colouring below only matches `<a href="`.
            .'<a style="display:'.($centered ? 'block' : 'inline-block').';padding:14px 28px;font-size:15px;font-weight:600;color:'.$onPrimary.';text-decoration:none;border-radius:10px;text-align:center;" href="'.$match[1].'">'.strip_tags($match[3]).'</a></td></tr></table>';
        $html = (string) preg_replace_callback('#<p>\s*<a href="([^"]+)"([^>]*)>(.*?)</a>\s*</p>#is', $button, $html);

        // Two-cell rows = detail rows (label muted at the start, value bold at the end).
        $row = 0;
        $html = (string) preg_replace_callback('#<tr>\s*<td>(.*?)</td>\s*<td>(.*?)</td>\s*</tr>#is', static function (array $match) use (&$row, $start, $end): string {
            $background = $row++ % 2 === 0 ? '#f8f5f2' : '#ffffff';

            return '<tr style="background:'.$background.';"><td style="padding:12px 16px;font-size:14px;color:#7a6a62;text-align:'.$start.';">'.$match[1].'</td>'
                .'<td style="padding:12px 16px;font-size:14px;font-weight:600;color:#2b1a14;text-align:'.$end.';">'.$match[2].'</td></tr>';
        }, $html);

        return strtr($html, [
            '<p>' => '<p style="margin:0 0 16px;">',
            '<h2>' => '<h2 style="margin:0 0 16px;font-size:24px;line-height:32px;font-weight:700;text-align:'.($centered ? 'center' : $start).';color:#2b1a14;">',
            '<h3>' => '<h3 style="margin:0 0 12px;font-size:17px;line-height:1.3;">',
            '<blockquote>' => '<blockquote style="margin:0 0 16px;padding:14px 16px;border-radius:10px;background:#fdf1e3;color:#2b1a14;">',
            '<a href="' => '<a style="color:'.$primary.';" href="',
            '<table>' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border:1px solid #eee7e2;border-radius:10px;border-collapse:separate;overflow:hidden;">',
            '<td>' => '<td style="padding:8px 10px;border-bottom:1px solid #eee7e2;">',
            '<th>' => '<th style="padding:8px 10px;border-bottom:1px solid #eee7e2;text-align:'.$start.';">',
            '<ul>' => '<ul style="margin:0 0 16px;padding-inline-start:20px;">',
            '<ol>' => '<ol style="margin:0 0 16px;padding-inline-start:20px;">',
            '<img ' => '<img style="max-width:100%;height:auto;" ',
            '<code>' => '<code style="font-family:monospace;background:#f5f3f1;padding:1px 4px;border-radius:4px;">',
        ]);
    }

    /**
     * Readable text colour on a brand background: white, or the dark ink for light brands.
     */
    public static function onColor(string $background): string
    {
        $hex = ltrim(trim($background), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-f]{6}$/i', $hex) !== 1) {
            return '#ffffff';
        }

        $channel = static function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel(substr($hex, 0, 2)) + 0.7152 * $channel(substr($hex, 2, 2)) + 0.0722 * $channel(substr($hex, 4, 2));

        // White (the Figma button label) while it keeps at least 3:1 against the brand.
        return 1.05 / ($luminance + 0.05) >= 3 ? '#ffffff' : '#2b1a14';
    }

    /**
     * Hidden inbox preview line: the start of the body text.
     */
    public static function preheader(string $html): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ' ', $html)), ENT_QUOTES | ENT_HTML5)));

        return mb_strlen($text) > 140 ? rtrim(mb_substr($text, 0, 137)).'…' : $text;
    }
}
