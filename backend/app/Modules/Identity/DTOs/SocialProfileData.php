<?php

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\SocialProvider;
use App\Shared\DTOs\BaseDTO;

/**
 * The verified identity a social provider returned (Google userinfo / Apple id_token).
 */
final class SocialProfileData extends BaseDTO
{
    public function __construct(
        public readonly SocialProvider $provider,
        public readonly string $id,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name = null,
    ) {}
}
