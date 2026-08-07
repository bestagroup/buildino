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
        | Files
        |--------------------------------------------------------------------------
        */

        Schema::create('files', function (Blueprint $table) {


            $table->id();



            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();



            $table->string('disk')
                ->default('public');



            $table->string('path');



            $table->string('original_name');



            $table->string('mime_type')
                ->nullable();



            $table->unsignedBigInteger('size')
                ->nullable();



            $table->enum('category',[

                'building',
                'unit',
                'financial',
                'support',
                'profile',
                'other'

            ])
                ->default('other');



            $table->timestamps();


            $table->softDeletes();



            $table->index([
                'category',
                'uploaded_by'
            ]);

        });






        /*
        |--------------------------------------------------------------------------
        | File Relations
        | Polymorphic Attachment
        |--------------------------------------------------------------------------
        */

        Schema::create('file_relations', function (Blueprint $table) {


            $table->id();



            $table->foreignId('file_id')
                ->constrained()
                ->cascadeOnDelete();



            $table->string('related_type');



            $table->unsignedBigInteger('related_id');



            $table->string('purpose')
                ->nullable();



            $table->timestamps();



            $table->index([
                'related_type',
                'related_id'
            ]);

        });





        /*
        |--------------------------------------------------------------------------
        | File Downloads
        |--------------------------------------------------------------------------
        */

        Schema::create('file_downloads', function (Blueprint $table) {


            $table->id();



            $table->foreignId('file_id')
                ->constrained()
                ->cascadeOnDelete();



            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();



            $table->ipAddress('ip_address')
                ->nullable();



            $table->timestamp('downloaded_at');



            $table->timestamps();



        });


    }



    public function down(): void
    {

        Schema::dropIfExists('file_downloads');
        Schema::dropIfExists('file_relations');
        Schema::dropIfExists('files');

    }

};
