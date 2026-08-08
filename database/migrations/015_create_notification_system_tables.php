<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('type', 30)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->morphs('target');
            $table->timestamps();
            $table->unique(['announcement_id', 'target_type', 'target_id'], 'announcement_target_unique');
        });

        Schema::create('announcement_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notifiable');
            $table->string('notification_type')->nullable();
            $table->string('channel', 20);
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('announcement_receipts');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcements');
    }
};
