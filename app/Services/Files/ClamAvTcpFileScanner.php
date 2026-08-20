<?php

namespace App\Services\Files;

use App\Contracts\FileScanner;
use App\Enums\FileScanStatus;
use Closure;

final class ClamAvTcpFileScanner implements FileScanner
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeoutSeconds,
        private readonly ?Closure $transport = null
    ) {}

    public function scan(string $absolutePath): FileScanStatus
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return FileScanStatus::Failed;
        }

        try {
            $response = $this->transport
                ? ($this->transport)($absolutePath)
                : $this->streamToClamAv($absolutePath);
        } catch (\Throwable) {
            return FileScanStatus::Failed;
        }

        if (! is_string($response)) {
            return FileScanStatus::Failed;
        }

        if (str_contains($response, 'FOUND')) {
            return FileScanStatus::Infected;
        }

        return str_contains($response, 'OK')
            ? FileScanStatus::Clean
            : FileScanStatus::Failed;
    }

    private function streamToClamAv(string $absolutePath): string
    {
        $errno = 0;
        $error = '';
        $socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $error,
            $this->timeoutSeconds
        );

        if (! is_resource($socket)) {
            throw new \RuntimeException(
                "Unable to connect to ClamAV: {$error} ({$errno})."
            );
        }

        stream_set_timeout($socket, $this->timeoutSeconds);
        $file = fopen($absolutePath, 'rb');

        if (! is_resource($file)) {
            fclose($socket);
            throw new \RuntimeException('Unable to open the uploaded file.');
        }

        try {
            $this->writeAll($socket, "zINSTREAM\0");

            while (! feof($file)) {
                $chunk = fread($file, 8192);

                if ($chunk === false) {
                    throw new \RuntimeException('Unable to read the uploaded file.');
                }

                if ($chunk !== '') {
                    $this->writeAll(
                        $socket,
                        pack('N', strlen($chunk)).$chunk
                    );
                }
            }

            $this->writeAll($socket, pack('N', 0));
            stream_socket_shutdown($socket, STREAM_SHUT_WR);
            $response = stream_get_contents($socket);

            if ($response === false) {
                throw new \RuntimeException('Unable to read the ClamAV response.');
            }

            return $response;
        } finally {
            fclose($file);
            fclose($socket);
        }
    }

    /** @param resource $stream */
    private function writeAll($stream, string $payload): void
    {
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            $written = fwrite($stream, substr($payload, $offset));

            if ($written === false || $written === 0) {
                throw new \RuntimeException('Unable to stream data to ClamAV.');
            }

            $offset += $written;
        }
    }
}
