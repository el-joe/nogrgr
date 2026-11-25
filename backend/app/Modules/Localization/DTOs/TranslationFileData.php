<?php

namespace App\Modules\Localization\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * A new panel translation file created in the editor (ADM-092): `namespace` null = app
 * `lang/*`, otherwise a module key; it exists through its first key's overrides.
 */
final class TranslationFileData extends BaseDTO
{
    /**
     * @param  array<string, string|null>  $values  locale => value
     */
    public function __construct(
        public readonly ?string $namespace,
        public readonly string $name,
        public readonly string $key,
        public readonly array $values,
    ) {}

    public function fileId(): string
    {
        return $this->namespace === null || $this->namespace === '' ? $this->name : $this->namespace.'::'.$this->name;
    }
}
