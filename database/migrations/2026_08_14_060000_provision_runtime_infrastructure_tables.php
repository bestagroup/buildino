<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Buildino already uses database cache / database queues in
         * configuration. Older snapshots did not contain Laravel's
         * standard cache/failed-job/batch migrations, so provision them
         * defensively only when they are actually missing.
         */

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique('failed_jobs_uuid_uq');
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('system_runtime_heartbeats')) {
            Schema::create(
                'system_runtime_heartbeats',
                function (Blueprint $table): void {
                    $table->string('name', 120)->primary();
                    $table->timestamp('last_seen_at')->index();
                    $table->string('host', 190)->nullable();
                    $table->unsignedBigInteger('process_id')->nullable();
                    $table->json('metadata')->nullable();
                    $table->timestamps();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Standard Laravel runtime tables are intentionally preserved.
         * They may predate this migration or contain queue/cache audit
         * state. Only the Buildino-specific heartbeat table is removed.
         */
        Schema::dropIfExists('system_runtime_heartbeats');
    }
};
