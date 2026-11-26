<?php

namespace App\Modules\Platform\Exceptions;

/**
 * The vendor compliance center refused an action (profile under review / verified).
 * Renders like every PlatformRuleException: 422 {success: false, message}.
 */
class ComplianceCenterException extends PlatformRuleException
{
    public static function locked(): self
    {
        return new self(__('platform::vendor_compliance.errors.locked'));
    }

    public static function documentNotRemovable(): self
    {
        return new self(__('platform::vendor_compliance.errors.document_locked'));
    }

    public static function uploadFailed(): self
    {
        return new self(__('platform::vendor_compliance.errors.upload_failed'));
    }
}
