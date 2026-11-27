<?php

namespace App\Modules\Storefront\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Field types of a theme.json `settings` entry (VEN-036b form generated from the schema).
 */
enum CustomThemeSettingType: string
{
    use HasEnumHelpers;

    case Color = 'color';
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Toggle = 'toggle';
    case Font = 'font';

    public static function translationKey(): string
    {
        return 'content::custom_themes.enums.setting_type';
    }
}
