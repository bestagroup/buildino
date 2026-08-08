<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('private');
            $table->string('visibility', 20)->default('private');
            $table->string('path');
            $table->string('stored_name');
            $table->string('original_name');
            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 128)->nullable()->index();
            $table->string('category')->default('other')->index();
            $table->boolean('is_confidential')->default(false);
            $table->string('scan_status', 20)->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('file_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->morphs('related');
            $table->string('purpose')->nullable();
            $table->timestamps();
            $table->unique(['file_id', 'related_type', 'related_id', 'purpose'], 'file_relation_unique');
        });

        Schema::create('file_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('downloaded_at');
            $table->timestamps();
            $table->index(['file_id', 'downloaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_downloads');
        Schema::dropIfExists('file_relations');
        Schema::dropIfExists('files');
    }
};
