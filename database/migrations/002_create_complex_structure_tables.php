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
        | Complexes
        |--------------------------------------------------------------------------
        */

        Schema::create('complexes', function (Blueprint $table) {

            $table->id();

            $table->string('code')->unique();
            $table->string('title');

            $table->string('manager_name')->nullable();
            $table->string('manager_mobile', 20)->nullable();

            $table->string('province');
            $table->string('city');

            $table->text('address')->nullable();

            $table->integer('latitude')->nullable();
            $table->integer('longitude')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('is_active');
            $table->index(['province', 'city']);
        });

        /*
        |--------------------------------------------------------------------------
        | Buildings
        |--------------------------------------------------------------------------
        */

        Schema::create('buildings', function (Blueprint $table) {

            $table->id();

            $table->foreignId('complex_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('code')->unique();

            $table->string('title');

            $table->string('building_number')->nullable();

            $table->unsignedInteger('floors_count')->default(0);
            $table->unsignedInteger('units_count')->default(0);

            $table->unsignedInteger('parking_count')->default(0);
            $table->unsignedInteger('storage_count')->default(0);

            $table->unsignedSmallInteger('construction_year')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('complex_id');
            $table->index('is_active');
        });

        /*
        |--------------------------------------------------------------------------
        | Blocks
        |--------------------------------------------------------------------------
        */

        Schema::create('blocks', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['building_id', 'title']);
        });

        /*
        |--------------------------------------------------------------------------
        | Floors
        |--------------------------------------------------------------------------
        */

        Schema::create('floors', function (Blueprint $table) {

            $table->id();

            $table->foreignId('block_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->integer('floor_number');

            $table->string('title')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['block_id', 'floor_number']);
        });

        /*
        |--------------------------------------------------------------------------
        | Units
        |--------------------------------------------------------------------------
        */

        Schema::create('units', function (Blueprint $table) {

            $table->id();

            $table->foreignId('floor_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('unit_number');

            $table->string('title')->nullable();

            $table->integer('area')->nullable();

            $table->unsignedTinyInteger('bedrooms')->nullable();

            $table->enum('usage_type', ['residential', 'commercial', 'office'])->default('residential');

            $table->enum('ownership_status', ['owner_occupied', 'tenant_occupied', 'vacant'])->default('vacant');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['floor_id', 'unit_number']);

            $table->index('ownership_status');
            $table->index('usage_type');
        });

        /*
        |--------------------------------------------------------------------------
        | Parking Spaces
        |--------------------------------------------------------------------------
        */

        Schema::create('parking_spaces', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('parking_number');

            $table->string('title')->nullable();

            $table->enum('type', ['private', 'shared', 'guest'])->default('private');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('parking_number');
        });

        /*
        |--------------------------------------------------------------------------
        | Storage Units
        |--------------------------------------------------------------------------
        */

        Schema::create('storage_units', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('storage_number');

            $table->integer('area')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('storage_number');
        });

        /*
        |--------------------------------------------------------------------------
        | Building Emergency Contacts
        |--------------------------------------------------------------------------
        */

        Schema::create('building_emergency_contacts', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->string('phone', 30);

            $table->string('description')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Building Rules
        |--------------------------------------------------------------------------
        */

        Schema::create('building_rules', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->longText('content');

            $table->boolean('is_active')->default(true);

            $table->date('effective_from')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        /*
        |--------------------------------------------------------------------------
        | Building Documents
        |--------------------------------------------------------------------------
        */

        Schema::create('building_documents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->enum('type', ['document', 'minutes', 'contract', 'image', 'other'])->default('document');

            $table->string('file_name');

            $table->string('file_path');

            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });

        /*
        |--------------------------------------------------------------------------
        | Unit Documents
        |--------------------------------------------------------------------------
        */

        Schema::create('unit_documents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('unit_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->enum('type', ['ownership', 'lease', 'insurance', 'image', 'other'])->default('other');

            $table->string('file_name');

            $table->string('file_path');

            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('unit_documents');
        Schema::dropIfExists('building_documents');
        Schema::dropIfExists('building_rules');
        Schema::dropIfExists('building_emergency_contacts');
        Schema::dropIfExists('storage_units');
        Schema::dropIfExists('parking_spaces');
        Schema::dropIfExists('units');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('complexes');
    }
};
