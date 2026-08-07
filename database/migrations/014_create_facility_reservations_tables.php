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
        | Facility Reservations
        |--------------------------------------------------------------------------
        */

        Schema::create('facility_reservations', function (Blueprint $table) {

            $table->id();


            $table->foreignId('building_facility_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();


            $table->foreignId('facility_time_slot_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();


            $table->foreignId('unit_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();


            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();


            $table->date('reservation_date');


            $table->time('start_time');


            $table->time('end_time');


            $table->decimal('amount',12,2)
                ->default(0);


            $table->enum('status',[
                'pending',
                'approved',
                'rejected',
                'cancelled',
                'completed'
            ])
                ->default('pending');


            $table->enum('approval_type',[
                'automatic',
                'manual'
            ])
                ->default('manual');


            $table->text('description')
                ->nullable();


            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            $table->timestamp('approved_at')
                ->nullable();


            $table->timestamps();

            $table->softDeletes();


            $table->index(
                [
                    'building_facility_id',
                    'reservation_date'
                ],
                'facility_reservation_date_idx'
            );

            $table->index(
                [
                    'unit_id',
                    'status'
                ],
                'unit_reservation_status_idx'
            );

        });



        /*
        |--------------------------------------------------------------------------
        | Reservation Payments
        |--------------------------------------------------------------------------
        */

        Schema::create('reservation_payments', function (Blueprint $table) {

            $table->id();


            $table->foreignId('facility_reservation_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('payment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();


            $table->decimal('amount',12,2);


            $table->enum('status',[
                'pending',
                'paid',
                'failed',
                'refunded'
            ])
                ->default('pending');


            $table->timestamps();


            $table->unique('facility_reservation_id');

        });



        /*
        |--------------------------------------------------------------------------
        | Reservation Cancellations
        |--------------------------------------------------------------------------
        */

        Schema::create('reservation_cancellations', function (Blueprint $table) {

            $table->id();


            $table->foreignId('facility_reservation_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            $table->text('reason')
                ->nullable();


            $table->decimal('refund_amount',12,2)
                ->default(0);


            $table->timestamp('cancelled_at');


            $table->timestamps();

        });




        /*
        |--------------------------------------------------------------------------
        | Reservation Notifications
        |--------------------------------------------------------------------------
        */

        Schema::create('reservation_notifications', function (Blueprint $table) {

            $table->id();


            $table->foreignId('facility_reservation_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->enum('type',[
                'created',
                'approved',
                'rejected',
                'cancelled',
                'reminder',
                'changed'
            ]);


            $table->boolean('is_read')
                ->default(false);


            $table->timestamp('sent_at')
                ->nullable();


            $table->timestamps();


            $table->index([
                'user_id',
                'is_read'
            ]);

        });

    }


    public function down(): void
    {
        Schema::dropIfExists('reservation_notifications');
        Schema::dropIfExists('reservation_cancellations');
        Schema::dropIfExists('reservation_payments');
        Schema::dropIfExists('facility_reservations');
    }
};
