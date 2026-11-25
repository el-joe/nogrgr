<?php

namespace App\Modules\Integrations\DTOs;

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Validated values of one Integrations card. Blank secrets are absent (= keep the
 * stored one); `files` holds uploaded key files (Apple .p8).
 */
final class IntegrationSettingsData extends BaseDTO
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     * @param  array<string, UploadedFile>  $files
     */
    public function __construct(
        public readonly IntegrationSection $section,
        public readonly array $values,
        public readonly array $files = [],
    ) {}
}
