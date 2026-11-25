<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Badge of an integration card / gateway config, from its last connection test.
 */
enum ConnectionStatus: string
{
    use HasEnumHelpers;

    case Connected = 'connected';
    case Failed = 'failed';
    case NotTested = 'not_tested';
    case NotConfigured = 'not_configured';

    public function tone(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::Failed => 'danger',
            self::NotTested => 'warning',
            self::NotConfigured => 'neutral',
        };
    }
}
