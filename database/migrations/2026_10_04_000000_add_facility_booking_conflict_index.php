<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('facility_reservations')
            && ! Schema::hasIndex(
                'facility_reservations',
                'fr_booking_conflict_idx'
            )
        ) {
            Schema::table(
                'facility_reservations',
                function (Blueprint $table): void {
                    $table->index(
                        [
                            'building_facility_id',
                            'reservation_date',
                            'status',
                            'start_time',
                            'end_time',
                        ],
                        'fr_booking_conflict_idx'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('facility_reservations')
            && Schema::hasIndex(
                'facility_reservations',
                'fr_booking_conflict_idx'
            )
        ) {
            Schema::table(
                'facility_reservations',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'fr_booking_conflict_idx'
                    );
                }
            );
        }
    }
};
