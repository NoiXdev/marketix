<?php

namespace App\Services;

class SystemCertificateReader implements CertificateReader
{
    public function read(string $host, int $port = 443): ?array
    {
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
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            return null;
        }

        $parsed = openssl_x509_parse($cert);

        if (! is_array($parsed)) {
            return null;
        }

        $san = [];
        if (! empty($parsed['extensions']['subjectAltName'])) {
            foreach (explode(',', $parsed['extensions']['subjectAltName']) as $entry) {
                $entry = trim($entry);
                if (str_starts_with($entry, 'DNS:')) {
                    $san[] = substr($entry, 4);
                }
            }
        }

        return [
            'valid_to' => $parsed['validTo_time_t'] ?? 0,
            'cn' => $parsed['subject']['CN'] ?? '',
            'san' => $san,
        ];
    }
}
