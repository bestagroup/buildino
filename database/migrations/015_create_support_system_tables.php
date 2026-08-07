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
        | Support Categories
        |--------------------------------------------------------------------------
        */
        Schema::create('support_categories', function(Blueprint $table){

            $table->id();

            $table->string('title');

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
        /*
        |--------------------------------------------------------------------------
        | Support Tickets
        |--------------------------------------------------------------------------
        */
        Schema::create('support_tickets', function(Blueprint $table){

            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('support_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('ticket_number')->unique();

            $table->string('subject');

            $table->text('description');

            $table->enum('priority',['low', 'medium', 'high', 'urgent'])->default('medium');

            $table->enum('status',['open', 'pending', 'answered', 'closed'])->default('open');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();
        });
        /*
        |--------------------------------------------------------------------------
        | Support Messages
        |--------------------------------------------------------------------------
        */
        Schema::create('support_messages', function(Blueprint $table){

            $table->id();

            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('message');

            $table->boolean('is_internal')->default(false);

            $table->timestamps();
        });
        /*
        |--------------------------------------------------------------------------
        | Support Attachments
        |--------------------------------------------------------------------------
        */
        Schema::create('support_attachments', function(Blueprint $table){

            $table->id();

            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();

            $table->string('file_name');

            $table->string('file_path');

            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_categories');

    }

};
