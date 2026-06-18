<?php

namespace App\Services;

interface CertificateReader
{
    /**
     * Read the TLS certificate served at host:port.
     *
     * @return array{valid_to: int, cn: string, san: string[]}|null Null if no cert could be read.
     */
    public function read(string $host, int $port = 443): ?array;
}
