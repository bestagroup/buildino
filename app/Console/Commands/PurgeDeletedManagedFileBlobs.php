<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeDeletedManagedFileBlobs extends Command
{
    protected $signature =
        'files:purge-deleted
        {--dry-run : Report orphaned blobs without deleting them}';

    protected $description =
        'Reconcile soft-deleted managed file rows with physical storage';

    public function handle(): int
    {
        $dryRun = (bool) $this->option(
            'dry-run'
        );

        $checked = 0;
        $deleted = 0;
        $missing = 0;
        $failed = 0;

        File::onlyTrashed()
            ->orderBy('id')
            ->chunkById(
                200,
                function ($files) use (
                    $dryRun,
                    &$checked,
                    &$deleted,
                    &$missing,
                    &$failed
                ): void {
                    foreach ($files as $file) {
                        $checked++;

                        $storage = Storage::disk(
                            $file->disk
                        );

                        if (! $storage->exists(
                            $file->path
                        )) {
                            $missing++;
                            continue;
                        }

                        if ($dryRun) {
                            continue;
                        }

                        if ($storage->delete(
                            $file->path
                        )) {
                            $deleted++;
                        } else {
                            $failed++;
                        }
                    }
                }
            );

        $this->table(
            [
                'Checked',
                'Deleted',
                'Already missing',
                'Failed',
                'Dry run',
            ],
            [[
                $checked,
                $deleted,
                $missing,
                $failed,
                $dryRun ? 'yes' : 'no',
            ]]
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
