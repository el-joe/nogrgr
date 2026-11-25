<?php

namespace App\Modules\Content\DTOs;

use App\Modules\Content\Enums\FaqGroup;
use App\Shared\DTOs\BaseDTO;

/**
 * FAQ form (ADM-083a).
 */
final class FaqData extends BaseDTO
{
    /**
     * @param  array<string, string>  $question
     * @param  array<string, string>  $answer  sanitised HTML
     */
    public function __construct(
        public readonly FaqGroup $group,
        public readonly array $question,
        public readonly array $answer,
        public readonly bool $isActive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'group' => $this->group,
            'question' => $this->question,
            'answer' => $this->answer,
            'is_active' => $this->isActive,
        ];
    }
}
