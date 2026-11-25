<?php

namespace App\Modules\Identity\DTOs;

use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * My profile (ADM-005): identity fields, language, timezone and avatar. `avatar` is a
 * new upload; `keepAvatar` false (and no upload) removes the current one.
 */
final class AdminProfileData extends BaseDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $locale,
        public readonly ?string $timezone,
        public readonly ?UploadedFile $avatar = null,
        public readonly bool $keepAvatar = true,
    ) {}
}
