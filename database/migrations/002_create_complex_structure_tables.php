<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complexes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('province');
            $table->string('city');
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->integer('latitude')->nullable();
            $table->integer('longitude')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['province', 'city']);
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complex_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('building_number')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->integer('latitude')->nullable();
            $table->integer('longitude')->nullable();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->string('currency', 3)->default('IRR');
            $table->unsignedInteger('floors_count')->default(0);
            $table->unsignedInteger('units_count')->default(0);
            $table->unsignedInteger('parking_count')->default(0);
            $table->unsignedInteger('storage_count')->default(0);
            $table->unsignedSmallInteger('construction_year')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('complex_id');
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['building_id', 'title']);
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('block_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('floor_number');
            $table->string('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['block_id', 'floor_number']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('unit_number');
            $table->string('title')->nullable();
            $table->integer('area')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->string('usage_type', 30)->default('residential')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['floor_id', 'unit_number']);
        });

        Schema::create('parking_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('parking_number');
            $table->string('title')->nullable();
            $table->string('type', 30)->default('private');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['building_id', 'parking_number']);
        });

        Schema::create('storage_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->string('storage_number');
            $table->integer('area')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['building_id', 'storage_number']);
        });

        Schema::create('unit_parking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('parking_space_id')->constrained()->restrictOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'parking_space_id']);
        });

        Schema::create('unit_storage_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_unit_id')->constrained()->restrictOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'storage_unit_id']);
        });

        Schema::create('building_emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->string('phone', 30);
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('building_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_rules');
        Schema::dropIfExists('building_emergency_contacts');
        Schema::dropIfExists('unit_storage_assignments');
        Schema::dropIfExists('unit_parking_assignments');
        Schema::dropIfExists('storage_units');
        Schema::dropIfExists('parking_spaces');
        Schema::dropIfExists('units');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('complexes');
    }
};
