<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_invitations', function (Blueprint $table): void {
            $table->foreignId('invited_user_id')
                ->nullable()
                ->after('invited_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('message')
                ->nullable()
                ->after('channel');
            $table->index([
                'unit_id',
                'invited_user_id',
                'status',
            ], 'unit_invitation_recipient_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('unit_invitations', function (Blueprint $table): void {
            $table->dropIndex('unit_invitation_recipient_status_index');
            $table->dropConstrainedForeignId('invited_user_id');
            $table->dropColumn('message');
        });
    }
};
