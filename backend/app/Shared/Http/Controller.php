<?php

namespace App\Shared\Http;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base for every module controller. Controllers stay thin:
 * FormRequest → DTO::fromRequest() → Service → JsonResponder / view.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
