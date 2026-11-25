<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\SettingFieldType;
use App\Shared\DTOs\BaseDTO;

/**
 * One configurable value of an integration section or gateway config. `required`
 * applies while the section/config is enabled; secrets already stored may be left
 * blank (kept). `options` = value => label for selects.
 */
final class SettingField extends BaseDTO
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public readonly string $key,
        public readonly SettingFieldType $type = SettingFieldType::Text,
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly string|int|bool|null $default = null,
        public readonly int $maxLength = 255,
        public readonly ?string $accept = null,
    ) {}

    public function isSecret(): bool
    {
        return $this->type->isSecret();
    }

    /**
     * Validation rules of the submitted value (presence rules are added by the request).
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        return match ($this->type) {
            SettingFieldType::Toggle => ['boolean'],
            SettingFieldType::Select => ['string', 'in:'.implode(',', array_keys($this->options))],
            SettingFieldType::Email => ['string', 'email:rfc', 'max:'.$this->maxLength],
            SettingFieldType::Url => ['string', 'url:http,https', 'max:'.$this->maxLength],
            SettingFieldType::Number => ['integer', 'min:1', 'max:65535'],
            SettingFieldType::File => ['file', 'max:16'],
            default => ['string', 'max:'.$this->maxLength],
        };
    }
}
