<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Life cycle of an uploaded custom Twig theme version (decision #121):
 * failed (package rejected by the validator) · draft (valid, not submitted) ·
 * pending_review (in the platform queue, ADM-089) · approved (may go live) · rejected.
 * "Live" is not a status: it is the theme's `live_version_id` (always an approved version).
 */
enum CustomThemeVersionStatus: string
{
    use HasEnumHelpers;

    case Failed = 'failed';
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public static function translationKey(): string
    {
        return 'content::custom_themes.enums.status';
    }

    /**
     * Badge tone of the shared status pill (x-ui::badge).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Failed, self::Rejected => 'danger',
            self::Draft => 'neutral',
            self::PendingReview => 'warning',
            self::Approved => 'success',
        };
    }

    public function canSubmit(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }

    public function canPreview(): bool
    {
        return $this !== self::Failed;
    }
}
