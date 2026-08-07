<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{

    public function up(): void
    {


        Schema::create('unit_invoices',function(Blueprint $table){

            $table->id();


            $table->foreignId('unit_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->string('invoice_number')
                ->unique();


            $table->date('issue_date');


            $table->date('due_date');


            $table->decimal('total_amount',12,2);


            $table->decimal('paid_amount',12,2)
                ->default(0);


            $table->enum('status',[
                'pending',
                'partial',
                'paid',
                'overdue'
            ])->default('pending');


            $table->timestamps();

        });



        Schema::create('invoice_items',function(Blueprint $table){

            $table->id();


            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->foreignId('charge_item_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->decimal('amount',12,2);


            $table->timestamps();

        });



        Schema::create('invoice_installments',function(Blueprint $table){

            $table->id();


            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->date('due_date');


            $table->decimal('amount',12,2);


            $table->enum('status',[
                'pending',
                'paid'
            ])->default('pending');


            $table->timestamps();

        });



        Schema::create('invoice_penalties',function(Blueprint $table){

            $table->id();


            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->decimal('amount',12,2);


            $table->string('reason')
                ->nullable();


            $table->timestamps();

        });



        Schema::create('invoice_discounts',function(Blueprint $table){

            $table->id();


            $table->foreignId('unit_invoice_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->decimal('amount',12,2);


            $table->string('reason')
                ->nullable();


            $table->timestamps();

        });


    }



    public function down():void
    {
        Schema::dropIfExists('invoice_discounts');
        Schema::dropIfExists('invoice_penalties');
        Schema::dropIfExists('invoice_installments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('unit_invoices');
    }

};
