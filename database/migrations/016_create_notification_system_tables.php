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
        | Announcements
        |--------------------------------------------------------------------------
        */

        Schema::create('announcements', function(Blueprint $table){


            $table->id();


            $table->foreignId('building_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();


            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            $table->string('title');


            $table->text('content');


            $table->enum('type',[
                'general',
                'urgent',
                'maintenance',
                'financial'
            ])
                ->default('general');



            $table->timestamp('published_at')
                ->nullable();



            $table->boolean('is_active')
                ->default(true);



            $table->timestamps();


        });




        /*
        |--------------------------------------------------------------------------
        | Announcement Receipts
        |--------------------------------------------------------------------------
        */

        Schema::create('announcement_receipts', function(Blueprint $table){


            $table->id();


            $table->foreignId('announcement_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->timestamp('read_at')
                ->nullable();


            $table->timestamps();


            $table->unique([
                'announcement_id',
                'user_id'
            ]);

        });




        /*
        |--------------------------------------------------------------------------
        | Notification Logs
        |--------------------------------------------------------------------------
        */

        Schema::create('notification_logs', function(Blueprint $table){


            $table->id();


            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();



            $table->string('channel');


            $table->string('title');



            $table->text('message');



            $table->enum('status',[
                'pending',
                'sent',
                'failed'
            ])
                ->default('pending');



            $table->timestamp('sent_at')
                ->nullable();



            $table->json('response')
                ->nullable();



            $table->timestamps();



        });

    }



    public function down(): void
    {

        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('announcement_receipts');
        Schema::dropIfExists('announcements');

    }

};
