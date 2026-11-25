<?php

namespace App\Modules\Notifications\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * A template rendered for one locale with variables replaced (values escaped).
 */
final class RenderedEmailData extends BaseDTO
{
    public function __construct(
        public readonly string $subject,
        public readonly string $html,
        public readonly string $locale,
    ) {}
}
