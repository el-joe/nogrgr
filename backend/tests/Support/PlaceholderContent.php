<?php

namespace Tests\Support;

/**
 * Phrases that mark unfinished pages ("coming soon", "under construction", lorem
 * ipsum…) in English and Arabic. Used by tests/Unit/Architecture/NoPlaceholderContentTest.php.
 *
 * Arabic "soon" is spelled قريبا / قريباً / قريبًا (tanween before or after the alef),
 * so the patterns accept every form. Bare "قريباً" only counts as a placeholder when it
 * is a whole string or element (a "Soon" label), so sentences such as
 * "ستصلك طلبيتك قريبًا" (your order arrives soon) stay allowed.
 */
final class PlaceholderContent
{
    private const SOON = 'قريب(?:اً|ًا|ا|ً)?';

    /**
     * @return list<string>
     */
    public static function patterns(): array
    {
        $soon = self::SOON;

        return [
            '/coming[\s_-]*soon/iu',
            '/under[\s_-]*construction/iu',
            '/lorem[\s_-]+ipsum/iu',
            '/not[\s_-]+(yet[\s_-]+)?implemented/iu',
            '/(?:سيتوفر|ستتوفر|سيتاح|ستتاح|متاح|متاحة|متوفر|متوفرة|قادم|قادمة|انتظرونا|ترقبوا)\s+'.$soon.'/u',
            '/(?:^|[\'"`>])\s*'.$soon.'(?:\s+جد(?:اً|ًا|ا))?\s*[.!…]?\s*(?:$|[\'"`<])/mu',
            '/(?:قيد|تحت)\s+(?:الإنشاء|الانشاء|التطوير|البناء|التنفيذ)/u',
            '/لم\s+(?:يتم\s+)?(?:تنفيذ(?:ها|ه)?|تفعيل(?:ها|ه)?)\s+بعد/u',
            '/لوريم\s+[إا]يبسوم/u',
            '/نص\s+تجريبي/u',
        ];
    }

    public static function matches(string $contents): ?string
    {
        foreach (self::patterns() as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                return $pattern;
            }
        }

        return null;
    }
}
