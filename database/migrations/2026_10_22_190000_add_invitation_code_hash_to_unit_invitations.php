<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'unit_invitations',
            function (Blueprint $table): void {
                $table->string(
                    'invitation_code_hash',
                    64
                )
                    ->nullable()
                    ->after('token');

                $table->unique(
                    'invitation_code_hash',
                    'unit_invitations_code_hash_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'unit_invitations',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'unit_invitations_code_hash_unique'
                );

                $table->dropColumn(
                    'invitation_code_hash'
                );
            }
        );
    }
};
