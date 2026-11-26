<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Keys of the tenant `store_settings` table.
 */
enum StoreSettingKey: string
{
    use HasEnumHelpers;

    /** Path of the store logo on the tenant `public` disk. */
    case LogoPath = 'logo_path';

    /** ISO timestamp: the panel tour was finished. */
    case TourCompletedAt = 'tour_completed_at';

    /** ISO timestamp: the panel tour was dismissed ("Skip tour"). */
    case TourDismissedAt = 'tour_dismissed_at';

    /** ISO timestamp: "Skip for now" on the payment-readiness step. */
    case PaymentReadinessSkippedAt = 'payment_readiness_skipped_at';

    /** ISO timestamp: the store was launched from the setup wizard (VEN-007 step 4). */
    case LaunchedAt = 'launched_at';

    /** Storefront theme per country (Storefront\Services\StoreThemeService): {"default": slug, "countries": {id: slug}}. */
    case StoreTheme = 'store_theme';

    /** bool: product reviews wait for the store's approval before they are public (Engagement\Services\Reviews\ReviewSettingsService, default false = publish). */
    case ReviewsRequireApproval = 'reviews_require_approval';

    /** Appearance › Colours & fonts (Storefront\Services\Appearance\StoreAppearanceService): {"primary": "#rrggbb"|null, "font_ltr": StoreFont|null, "font_rtl": StoreFont|null}. */
    case AppearanceColors = 'appearance_colors';

    /** Appearance › Promo bar: {"enabled": bool, "text": {locale: string}, "link": string|null, "background": "#rrggbb"}. */
    case PromoBar = 'promo_bar';

    /** Appearance › Footer: {"about": {locale: string}, "copyright": {locale: string}, "show_social": bool}. */
    case FooterSettings = 'footer_settings';

    /** Settings › Tracking pixels (Storefront\Services\Tracking\TrackingPixelService): {pixel: {"id": string|null, "enabled": bool}}. */
    case TrackingPixels = 'tracking_pixels';

    /** Settings › Mail (Notifications\Services\StoreMailSettingsService): SMTP host / port / encryption / username / encrypted password / from. */
    case MailSmtp = 'mail_smtp';

    /** Settings › General (Platform\Services\StoreGeneralSettingsService): shopper support e-mail (string). */
    case SupportEmail = 'support_email';

    /** Settings › General: the store's timezone identifier (string, default the app timezone). */
    case Timezone = 'timezone';

    /** Settings › Currencies (Localization\Services\StoreCurrencyService): {"inactive": [code], "not_default": [code]}. */
    case Currencies = 'currencies';

    /** Settings › Return policy (AfterSales\Services\ReturnPolicyService): stricter store override of the platform default. */
    case ReturnPolicy = 'return_policy';

    /** Mobile app (Api\Services\MobileAppSettingsService): releases per platform (min / latest version, store URL), maintenance + message, splash, feature switches. */
    case MobileApp = 'mobile_app';
}
