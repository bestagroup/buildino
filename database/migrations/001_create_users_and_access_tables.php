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
        | Users
        |--------------------------------------------------------------------------
        */

        Schema::create('users', function (Blueprint $table) {

            $table->id();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('national_code', 20)->nullable()->unique();

            $table->string('mobile', 20)->unique();
            $table->string('email')->nullable()->unique();

            $table->timestamp('mobile_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();

            $table->string('password')->nullable();

            $table->string('avatar')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocked')->default(false);

            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();

            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();

            $table->index('mobile');
            $table->index('email');
            $table->index('national_code');
            $table->index('is_active');
        });

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        Schema::create('roles', function (Blueprint $table) {

            $table->id();

            $table->string('name')->unique();
            $table->string('display_name');

            $table->text('description')->nullable();

            $table->boolean('is_system')->default(false);

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        Schema::create('permissions', function (Blueprint $table) {

            $table->id();

            $table->string('name')->unique();
            $table->string('display_name');

            $table->string('module')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('module');
        });

        /*
        |--------------------------------------------------------------------------
        | Role User
        |--------------------------------------------------------------------------
        */

        Schema::create('role_user', function (Blueprint $table) {

            $table->id();

            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | Permission Role
        |--------------------------------------------------------------------------
        */

        Schema::create('permission_role', function (Blueprint $table) {

            $table->id();

            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['permission_id', 'role_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | User Profiles
        |--------------------------------------------------------------------------
        */

        Schema::create('user_profiles', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->date('birth_date')->nullable();

            $table->enum('gender', [
                'male',
                'female'
            ])->nullable();

            $table->string('phone', 20)->nullable();

            $table->string('province')->nullable();
            $table->string('city')->nullable();

            $table->text('address')->nullable();

            $table->string('postal_code', 20)->nullable();

            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | User Devices
        |--------------------------------------------------------------------------
        */

        Schema::create('user_devices', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('device_id')->unique();

            $table->string('platform')->nullable();
            $table->string('device_name')->nullable();

            $table->text('push_token')->nullable();

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index('platform');
        });

        /*
        |--------------------------------------------------------------------------
        | OTP Codes
        |--------------------------------------------------------------------------
        */

        Schema::create('otp_codes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('mobile', 20);

            $table->string('code', 10);

            $table->timestamp('expires_at');

            $table->timestamp('verified_at')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            $table->index('mobile');
            $table->index('expires_at');
        });

        /*
        |--------------------------------------------------------------------------
        | Activity Logs
        |--------------------------------------------------------------------------
        */

        Schema::create('activity_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('module');

            $table->string('action');

            $table->string('model_type')->nullable();

            $table->unsignedBigInteger('model_id')->nullable();

            $table->ipAddress('ip_address')->nullable();

            $table->text('user_agent')->nullable();

            $table->json('properties')->nullable();

            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('module');
            $table->index('action');
        });

        /*
        |--------------------------------------------------------------------------
        | User Sessions
        |--------------------------------------------------------------------------
        */

        Schema::create('user_sessions', function (Blueprint $table) {

            $table->string('id')->primary();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();

            $table->text('user_agent')->nullable();

            $table->longText('payload');

            $table->unsignedBigInteger('last_activity');

            $table->timestamps();

            $table->index('user_id');
            $table->index('last_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
