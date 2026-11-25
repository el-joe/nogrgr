<?php

namespace App\Modules\Ai\Contracts;

/**
 * A model whose translatable fields the paid AI translation run (decision #44) must
 * translate. Every model with spatie translatable columns implements it so the
 * translation registry cannot skip one.
 */
interface AiTranslatable
{
    /**
     * Translatable attribute names, e.g. ['name', 'description'].
     *
     * @return list<string>
     */
    public function aiTranslatableFields(): array;
}
