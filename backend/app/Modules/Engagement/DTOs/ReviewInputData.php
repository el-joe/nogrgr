<?php

namespace App\Modules\Engagement\DTOs;

/**
 * A customer's review submission (SF-ECO-019a write / SF-ECO-028d).
 */
final readonly class ReviewInputData
{
    public function __construct(
        public int $rating,
        public string $comment,
        public ?string $title = null,
        public ?string $locale = null,
    ) {}
}
