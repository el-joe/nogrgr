<?php

namespace App\Modules\Platform\Contracts;

use Illuminate\Support\Carbon;

/**
 * Reads the TLS certificate a host serves on :443 (bound to StreamCertificateInspector; faked in tests).
 */
interface CertificateInspector
{
    /**
     * Expiry of a valid certificate for $host, or null when none / invalid / unreachable.
     */
    public function validUntil(string $host): ?Carbon;
}
