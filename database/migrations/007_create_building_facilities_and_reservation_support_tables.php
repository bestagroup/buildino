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
        | Building Facilities
        | امکانات ساختمان
        |--------------------------------------------------------------------------
        */

        Schema::create('building_facilities', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->string('code')->unique();

            $table->text('description')->nullable();

            $table->string('image')->nullable();

            $table->enum('type', ['sport','gym','pool','recreation','meeting','parking','service','roofgarden','other'])->default('other');

            $table->unsignedInteger('capacity')->nullable();

            $table->integer('default_price')->default(0);

            $table->boolean('requires_payment')->default(false);

            $table->boolean('requires_approval')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['building_id', 'is_active']);

        });
        /*
        |--------------------------------------------------------------------------
        | Facility Schedules
        |--------------------------------------------------------------------------
        */

        Schema::create('facility_schedules', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_facility_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');

            $table->time('end_time');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['building_facility_id', 'day_of_week', 'start_time'], 'facility_schedule_unique');

        });
        /*
        |--------------------------------------------------------------------------
        | Facility Time Slots
        |--------------------------------------------------------------------------
        */

        Schema::create('facility_time_slots', function (Blueprint $table) {

            $table->id();

            $table->foreignId('facility_schedule_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->time('start_time');

            $table->time('end_time');

            $table->unsignedInteger('capacity')->nullable();

            $table->integer('price')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
        /*
        |--------------------------------------------------------------------------
        | Facility Reservation Rules
        |--------------------------------------------------------------------------
        */

        Schema::create('facility_reservation_rules', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_facility_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->unsignedInteger('max_reservation_per_unit')->default(1);

            $table->unsignedInteger('max_duration_minutes')->default(60);

            $table->unsignedInteger('cancel_before_minutes')->default(60);

            $table->boolean('auto_confirm')->default(false);

            $table->timestamps();

        });
        /*
        |--------------------------------------------------------------------------
        | Facility Blackout Dates
        |--------------------------------------------------------------------------
        */

        Schema::create('facility_blackout_dates', function (Blueprint $table) {

            $table->id();


            $table->foreignId('building_facility_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->date('date');

            $table->time('start_time')->nullable();

            $table->time('end_time')->nullable();

            $table->string('reason')->nullable();

            $table->timestamps();

            $table->unique(['building_facility_id', 'date', 'start_time'], 'facility_blackout_unique');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_blackout_dates');
        Schema::dropIfExists('facility_reservation_rules');
        Schema::dropIfExists('facility_time_slots');
        Schema::dropIfExists('facility_schedules');
        Schema::dropIfExists('building_facilities');
    }
};
