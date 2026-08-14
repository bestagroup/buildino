<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('building_facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_time_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('final_amount')->default(0);
            $table->json('rule_snapshot')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->string('approval_type', 20)->default('manual');
            $table->text('description')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['building_facility_id', 'reservation_date'], 'fr_facility_date_idx');
            $table->index(['unit_id', 'status']);
        });

        Schema::create('reservation_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_reservation_id')->constrained()->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('cancellation_fee')->default(0);
            $table->unsignedBigInteger('refund_amount')->default(0);
            $table->string('refund_status', 20)->nullable();
            $table->foreignId('refund_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('cancelled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_cancellations');
        Schema::dropIfExists('facility_reservations');
    }
};
