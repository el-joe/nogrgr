<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Topics of Admin › System tools › Developer docs (ADM-115 / 115a, Figma 80:94467).
 * Content lives in `platform::developer_docs.topics.<value>` (en / ar) and describes the
 * platform as built (routes, headers, responses, theme resolution).
 */
enum DeveloperDocTopic: string
{
    use HasEnumHelpers;

    case GettingStarted = 'getting-started';
    case StoreApi = 'store-api';
    case Webhooks = 'webhooks';
    case MobileApi = 'mobile-api';
    case Themes = 'themes';

    public function label(): string
    {
        return __('platform::developer_docs.topics.'.$this->value.'.title');
    }

    public function icon(): string
    {
        return match ($this) {
            self::GettingStarted => 'info',
            self::StoreApi => 'link',
            self::Webhooks => 'zap',
            self::MobileApi => 'grid',
            self::Themes => 'palette',
        };
    }
}
