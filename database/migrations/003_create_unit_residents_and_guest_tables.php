<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        /*
        |--------------------------------------------------------------------------
        | Unit Residents
        | ارتباط کاربران با واحدها
        |--------------------------------------------------------------------------
        */

        Schema::create('unit_residents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->enum('resident_type', ['owner', 'tenant', 'family_member', 'representative']);

            $table->integer('ownership_percentage')->nullable();

            $table->date('start_date');

            $table->date('end_date')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->boolean('is_active')->default(true);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['unit_id', 'resident_type']);

            $table->index(['user_id', 'is_active']);

        });

        /*
        |--------------------------------------------------------------------------
        | Resident Histories
        | تاریخچه سکونت
        |--------------------------------------------------------------------------
        */

        Schema::create('resident_histories', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_resident_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('unit_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->enum('resident_type', ['owner', 'tenant', 'family_member', 'representative']);

            $table->date('start_date');

            $table->date('end_date')->nullable();

            $table->enum('change_reason', ['new_resident', 'ownership_transfer', 'lease_start', 'lease_end', 'moving_out', 'other'])->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['unit_id', 'start_date']);

        });
        /*
        |--------------------------------------------------------------------------
        | Unit Invitations
        | دعوت کاربران به واحد
        |--------------------------------------------------------------------------
        */

        Schema::create('unit_invitations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('invited_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('mobile',20);

            $table->string('email')->nullable();

            $table->enum('resident_type', ['owner', 'tenant', 'family_member', 'representative']);

            $table->string('token')->unique();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])->default('pending');

            $table->timestamp('expires_at');

            $table->timestamp('accepted_at')->nullable();

            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['mobile', 'status']);

        });
        /*
        |--------------------------------------------------------------------------
        | Unit Guests
        | مدیریت مهمانان
        |--------------------------------------------------------------------------
        */

        Schema::create('unit_guests', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('registered_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('first_name');

            $table->string('last_name');

            $table->string('mobile',20)->nullable();

            $table->string('national_code',20)->nullable();

            $table->string('vehicle_number')->nullable();

            $table->dateTime('expected_entry_at')->nullable();

            $table->dateTime('expected_exit_at')->nullable();

            $table->dateTime('entry_at')->nullable();

            $table->dateTime('exit_at')->nullable();

            $table->enum('status', ['invited', 'entered', 'exited', 'cancelled'])->default('invited');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['unit_id', 'status']);

            $table->index('mobile');

        });

    }

    public function down(): void
    {
        Schema::dropIfExists('unit_guests');
        Schema::dropIfExists('unit_invitations');
        Schema::dropIfExists('resident_histories');
        Schema::dropIfExists('unit_residents');
    }
};
