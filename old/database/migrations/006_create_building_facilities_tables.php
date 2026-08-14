<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('type', 30)->default('other');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedBigInteger('default_price')->default(0);
            $table->boolean('requires_payment')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['building_id', 'code']);
            $table->index(['building_id', 'is_active']);
        });

        Schema::create('facility_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['building_facility_id', 'day_of_week', 'start_time'], 'facility_schedule_unique');
        });

        Schema::create('facility_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_schedule_id')->constrained()->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['facility_schedule_id', 'start_time', 'end_time'], 'facility_time_slot_unique');
        });

        Schema::create('facility_reservation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_facility_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_duration_minutes')->nullable();
            $table->unsignedInteger('max_duration_minutes')->default(60);
            $table->unsignedInteger('min_advance_minutes')->default(0);
            $table->unsignedInteger('max_advance_days')->nullable();
            $table->unsignedInteger('max_reservations_per_day')->nullable();
            $table->unsignedInteger('max_reservations_per_week')->nullable();
            $table->unsignedInteger('max_reservations_per_month')->nullable();
            $table->unsignedInteger('max_reservation_per_unit')->default(1);
            $table->unsignedInteger('cancel_before_minutes')->default(60);
            $table->unsignedBigInteger('cancellation_fee')->default(0);
            $table->unsignedTinyInteger('refund_percentage')->default(100);
            $table->boolean('allow_guest')->default(false);
            $table->boolean('auto_confirm')->default(false);
            $table->timestamps();
        });

        Schema::create('facility_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_facility_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['building_facility_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_blackouts');
        Schema::dropIfExists('facility_reservation_rules');
        Schema::dropIfExists('facility_time_slots');
        Schema::dropIfExists('facility_schedules');
        Schema::dropIfExists('building_facilities');
    }
};
