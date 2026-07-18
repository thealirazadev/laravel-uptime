<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Reads a TLS certificate's expiry date. This is the one real-network seam in the
 * app; it is resolved from the container so tests can bind a fake. Certificate
 * validity is not judged here (the HTTP check owns reachability) — only expiry.
 */
class Ssl
{
    public function expiresAt(string $url): ?CarbonImmutable
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT) ?: 443;

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if ($cert === null) {
            return null;
        }

        $parsed = openssl_x509_parse($cert);

        if ($parsed === false || ! isset($parsed['validTo_time_t'])) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC($parsed['validTo_time_t']);
    }
}
