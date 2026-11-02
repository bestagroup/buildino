<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null keeps existing hierarchies unchanged; do not infer or merge old data.
        Schema::table('complexes', function (Blueprint $table): void {
            $table->string('workspace_type', 30)->nullable();
        });
        Schema::table('buildings', function (Blueprint $table): void {
            $table->string('structure_type', 30)->nullable();
            $table->timestamp('setup_completed_at')->nullable();
        });
        Schema::create('building_complex_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complex_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['complex_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_complex_requests');
        Schema::table('buildings', fn (Blueprint $table) => $table->dropColumn(['structure_type', 'setup_completed_at']));
        Schema::table('complexes', fn (Blueprint $table) => $table->dropColumn('workspace_type'));
    }
};
