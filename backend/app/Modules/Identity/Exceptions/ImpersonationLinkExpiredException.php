<?php

namespace App\Modules\Identity\Exceptions;

use RuntimeException;

/**
 * The impersonation accept link expired or was already used (VEN-005 error state).
 */
class ImpersonationLinkExpiredException extends RuntimeException {}
