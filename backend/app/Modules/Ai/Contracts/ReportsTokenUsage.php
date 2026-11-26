<?php

namespace App\Modules\Ai\Contracts;

/**
 * A client that counts the tokens its provider reported (usage logs of paid runs).
 */
interface ReportsTokenUsage
{
    /**
     * Tokens used since the client was built.
     *
     * @return array{prompt: int, completion: int}
     */
    public function tokenUsage(): array;
}
