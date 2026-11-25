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
}
