<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->string('module');
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('format', 20)->default('pdf');
            $table->string('status', 20)->default('processing')->index();
            $table->json('filters')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->string('type');
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dashboard_widget_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dashboard_widget_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_widgets');
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_definitions');
    }
};
