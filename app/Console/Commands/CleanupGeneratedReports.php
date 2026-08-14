<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\GeneratedReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupGeneratedReports extends Command
{
    protected $signature =
        'reports:cleanup
        {--dry-run : Show expired exports without deleting them}';

    protected $description =
        'Delete expired generated report files and detach them from report records';

    public function handle(): int
    {
        $dryRun =
            (bool) $this->option(
                'dry-run'
            );

        $processed = 0;
        $deleted = 0;
        $missing = 0;

        File::query()
            ->where(
                'category',
                'generated_report'
            )
            ->whereNotNull(
                'expires_at'
            )
            ->where(
                'expires_at',
                '<=',
                now()
            )
            ->orderBy('id')
            ->chunkById(
                200,
                function ($files) use (
                    $dryRun,
                    &$processed,
                    &$deleted,
                    &$missing
                ): void {
                    foreach ($files as $file) {
                        $processed++;

                        $exists =
                            Storage::disk(
                                $file->disk
                            )->exists(
                                $file->path
                            );

                        if (! $exists) {
                            $missing++;
                        }

                        if ($dryRun) {
                            continue;
                        }

                        if ($exists) {
                            Storage::disk(
                                $file->disk
                            )->delete(
                                $file->path
                            );
                        }

                        /*
                         * Preserve file_downloads audit rows. The files table
                         * is SoftDeletes-enabled and file_downloads uses a
                         * restrictive FK, so hard-deleting downloaded exports
                         * would violate the audit trail.
                         */
                        GeneratedReport::query()
                            ->where(
                                'file_id',
                                $file->getKey()
                            )
                            ->update([
                                'file_id' => null,
                            ]);

                        $file->delete();

                        $deleted++;
                    }
                }
            );

        $this->table(
            [
                'Expired',
                'Deleted',
                'Physical file missing',
                'Dry run',
            ],
            [[
                $processed,
                $deleted,
                $missing,
                $dryRun ? 'yes' : 'no',
            ]]
        );

        return self::SUCCESS;
    }
}
