<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->index(
            'support_tickets',
            ['building_id', 'status', 'priority', 'created_at'],
            'st_building_status_priority_idx'
        );

        $this->index(
            'support_tickets',
            ['user_id', 'status', 'created_at'],
            'st_user_status_created_idx'
        );

        $this->index(
            'support_messages',
            ['support_ticket_id', 'created_at'],
            'sm_ticket_created_idx'
        );

        $this->index(
            'service_requests',
            ['building_id', 'status', 'created_at'],
            'sr_building_status_created_idx'
        );

        $this->index(
            'service_requests',
            ['requested_by', 'status', 'created_at'],
            'sr_requester_status_created_idx'
        );

        $this->index(
            'service_requests',
            ['assigned_to', 'status', 'created_at'],
            'sr_provider_status_created_idx'
        );

        $this->index(
            'notification_logs',
            ['notifiable_type', 'notifiable_id', 'created_at'],
            'nl_notifiable_created_idx'
        );

        $this->index(
            'notification_logs',
            ['status', 'updated_at'],
            'nl_status_updated_idx'
        );
    }

    public function down(): void
    {
        $indexes = [
            'support_tickets' => [
                'st_building_status_priority_idx',
                'st_user_status_created_idx',
            ],
            'support_messages' => [
                'sm_ticket_created_idx',
            ],
            'service_requests' => [
                'sr_building_status_created_idx',
                'sr_requester_status_created_idx',
                'sr_provider_status_created_idx',
            ],
            'notification_logs' => [
                'nl_notifiable_created_idx',
                'nl_status_updated_idx',
            ],
        ];

        foreach ($indexes as $tableName => $names) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($names as $name) {
                if (! Schema::hasIndex($tableName, $name)) {
                    continue;
                }

                Schema::table(
                    $tableName,
                    fn (Blueprint $table) => $table->dropIndex($name)
                );
            }
        }
    }

    private function index(
        string $tableName,
        array $columns,
        string $name
    ): void {
        if (
            ! Schema::hasTable($tableName)
            || Schema::hasIndex($tableName, $name)
        ) {
            return;
        }

        Schema::table(
            $tableName,
            fn (Blueprint $table) => $table->index($columns, $name)
        );
    }
};
