<?php

namespace App\Jobs\Files;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteManagedFileBlobJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $disk,
        public readonly string $path
    ) {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(): void
    {
        $storage = Storage::disk(
            $this->disk
        );

        if (! $storage->exists($this->path)) {
            return;
        }

        if (! $storage->delete($this->path)) {
            throw new RuntimeException(
                'Managed file blob could not be deleted.'
            );
        }
    }

    public function tags(): array
    {
        return [
            'managed-file-delete',
            'disk:'.$this->disk,
            'path-hash:'.hash(
                'sha256',
                $this->path
            ),
        ];
    }
}
