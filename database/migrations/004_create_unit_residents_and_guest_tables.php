<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->integer('ownership_percentage')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['unit_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('unit_occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('occupancy_type', 30);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['unit_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::create('unit_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('relation_type', 30);
            $table->string('channel', 20);
            $table->string('token')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['mobile','email']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile', 20)->nullable()->index();
            $table->string('national_code', 20)->nullable()->index();
            $table->string('vehicle_plate')->nullable();
            $table->timestamps();
        });

        Schema::create('guest_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('expected_entry_at')->nullable();
            $table->dateTime('expected_exit_at')->nullable();
            $table->string('status', 20)->default('invited')->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'status']);
        });

        Schema::create('guest_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_visit_id')->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->dateTime('occurred_at')->index();
            $table->string('gate')->nullable();
            $table->string('entry_method', 30)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vehicle_plate')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_access_logs');
        Schema::dropIfExists('guest_visits');
        Schema::dropIfExists('guests');
        Schema::dropIfExists('unit_invitations');
        Schema::dropIfExists('unit_occupancies');
        Schema::dropIfExists('unit_ownerships');
    }
};
