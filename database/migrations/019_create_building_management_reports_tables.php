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
        | Report Definitions
        |--------------------------------------------------------------------------
        */
        Schema::create('report_definitions', function (Blueprint $table) {

            $table->id();

            $table->string('title');

            $table->string('code')->unique();

            $table->string('module');

            $table->json('configuration')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
        /*
        |--------------------------------------------------------------------------
        | Generated Reports
        |--------------------------------------------------------------------------
        */
        Schema::create('generated_reports', function (Blueprint $table) {

            $table->id();

            $table->foreignId('report_definition_id')->constrained()->cascadeOnDelete();

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('file_name')->nullable();

            $table->string('file_path')->nullable();

            $table->enum('format', ['pdf','excel','csv'])->default('pdf');

            $table->enum('status', ['processing','completed','failed'])->default('processing');

            $table->json('filters')->nullable();

            $table->timestamp('generated_at')->nullable();

            $table->timestamps();
        });
        /*
        |--------------------------------------------------------------------------
        | Dashboard Widgets
        |--------------------------------------------------------------------------
        */
        Schema::create('dashboard_widgets', function (Blueprint $table) {

            $table->id();

            $table->string('title');

            $table->string('code')->unique();

            $table->string('type');

            $table->json('configuration')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');
        Schema::dropIfExists('generated_reports');
        Schema::dropIfExists('report_definitions');

    }

};
