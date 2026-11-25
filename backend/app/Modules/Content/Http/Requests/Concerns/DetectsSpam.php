<?php

namespace App\Modules\Content\Http\Requests\Concerns;

/**
 * Honeypot for the public central forms: a hidden `website` field that humans leave
 * empty, and a `form_started_at` timestamp (bots submit instantly). Spam gets the same
 * success response and is never stored.
 *
 * @phpstan-require-extends \Illuminate\Foundation\Http\FormRequest
 */
trait DetectsSpam
{
    public function isSpam(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        $started = $this->input('form_started_at');

        return is_numeric($started) && (int) $started > 0 && now()->timestamp - (int) $started < 2;
    }
}
