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
        | User Login Histories
        |--------------------------------------------------------------------------
        */

        Schema::create('user_login_histories', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->ipAddress('ip_address')
                ->nullable();

            $table->string('device')
                ->nullable();

            $table->string('browser')
                ->nullable();

            $table->string('platform')
                ->nullable();

            $table->boolean('is_successful')
                ->default(true);

            $table->timestamp('login_at');

            $table->timestamps();


            $table->index([
                'user_id',
                'login_at'
            ]);

        });



        /*
        |--------------------------------------------------------------------------
        | User Access Logs
        |--------------------------------------------------------------------------
        */

        Schema::create('user_access_logs', function (Blueprint $table) {

            $table->id();


            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();


            $table->string('module');


            $table->string('action');


            $table->string('route')
                ->nullable();


            $table->json('parameters')
                ->nullable();


            $table->ipAddress('ip_address')
                ->nullable();


            $table->text('user_agent')
                ->nullable();


            $table->timestamps();



            $table->index([
                'module',
                'action'
            ]);

        });




        /*
        |--------------------------------------------------------------------------
        | User Preferences
        |--------------------------------------------------------------------------
        */

        Schema::create('user_preferences', function (Blueprint $table) {


            $table->id();


            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->boolean('receive_sms')
                ->default(true);


            $table->boolean('receive_email')
                ->default(true);


            $table->boolean('receive_push')
                ->default(true);


            $table->string('language')
                ->default('fa');


            $table->string('timezone')
                ->default('Asia/Tehran');


            $table->timestamps();


            $table->unique('user_id');

        });

    }


    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('user_access_logs');
        Schema::dropIfExists('user_login_histories');
    }
};
