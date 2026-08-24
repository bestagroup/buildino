<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_user_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('scope_type', 100);
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'scope_type', 'scope_id'],
                'managed_user_scope_unique'
            );
            $table->index(
                ['scope_type', 'scope_id'],
                'managed_user_scope_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_user_scopes');
    }
};
