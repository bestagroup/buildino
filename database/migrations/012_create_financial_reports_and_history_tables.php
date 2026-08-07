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
        | Invoice Payment History
        |--------------------------------------------------------------------------
        */

        Schema::create('invoice_payment_histories', function(Blueprint $table){


            $table->id();



            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();



            $table->foreignId('payment_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();



            $table->decimal(
                'amount',
                12,
                2
            );



            $table->dateTime('paid_at');



            $table->string('description')
                ->nullable();



            $table->timestamps();



            $table->index('unit_invoice_id');


        });





        /*
        |--------------------------------------------------------------------------
        | Financial Adjustments
        |--------------------------------------------------------------------------
        */

        Schema::create('financial_adjustments', function(Blueprint $table){


            $table->id();



            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();



            $table->enum('type',[

                'discount',
                'penalty',
                'correction',
                'refund'

            ]);



            $table->decimal(
                'amount',
                12,
                2
            );



            $table->text('reason');



            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();



            $table->timestamps();



        });






        /*
        |--------------------------------------------------------------------------
        | Financial Audit Logs
        |--------------------------------------------------------------------------
        */

        Schema::create('financial_audit_logs', function(Blueprint $table){


            $table->id();



            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();



            $table->string('action');



            $table->string('entity_type');



            $table->unsignedBigInteger('entity_id');



            $table->json('old_values')
                ->nullable();



            $table->json('new_values')
                ->nullable();



            $table->timestamps();



            $table->index([
                'entity_type',
                'entity_id'
            ]);


        });


    }



    public function down(): void
    {

        Schema::dropIfExists('financial_audit_logs');
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('invoice_payment_histories');

    }

};
