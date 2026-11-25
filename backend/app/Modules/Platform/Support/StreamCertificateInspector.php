<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Contracts\CertificateInspector;
use Illuminate\Support\Carbon;

final class StreamCertificateInspector implements CertificateInspector
{
    public function validUntil(string $host): ?Carbon
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client('ssl://'.$host.':443', $errorCode, $errorMessage, 8, STREAM_CLIENT_CONNECT, $context);

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($certificate === null) {
            return null;
        }

        $parsed = openssl_x509_parse($certificate);
        $validTo = is_array($parsed) ? ($parsed['validTo_time_t'] ?? null) : null;

        return is_int($validTo) && $validTo > time() ? Carbon::createFromTimestamp($validTo) : null;
    }
}
