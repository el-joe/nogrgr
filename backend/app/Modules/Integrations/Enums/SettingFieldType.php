<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Input kind of one integration setting. Secret values are encrypted at rest and
 * only ever shown masked; File stores the uploaded file's text content (Apple .p8).
 */
enum SettingFieldType: string
{
    use HasEnumHelpers;

    case Text = 'text';
    case Secret = 'secret';
    case Select = 'select';
    case Toggle = 'toggle';
    case Email = 'email';
    case Url = 'url';
    case Number = 'number';
    case File = 'file';
    case Textarea = 'textarea';
    case Ipv4 = 'ipv4';

    public function isSecret(): bool
    {
        return $this === self::Secret || $this === self::File;
    }
}
