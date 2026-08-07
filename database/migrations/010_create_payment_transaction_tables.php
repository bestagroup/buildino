<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{

    public function up():void
    {

        Schema::create('payments',function(Blueprint $table){

            $table->id();

            $table->foreignId('unit_invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('invoice_installment_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('amount');

            $table->enum('method',['online', 'manual', 'qr', 'cash']);

            $table->enum('status',['pending', 'success', 'failed', 'cancelled'])->default('pending');

            $table->timestamps();

        });

        Schema::create('payment_transactions',function(Blueprint $table){

            $table->id();

            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            $table->string('gateway')->nullable();

            $table->string('tracking_code')->nullable()->unique();

            $table->string('reference_number')->nullable();

            $table->json('response')->nullable();

            $table->timestamps();

        });

        Schema::create('payment_receipts',function(Blueprint $table){

            $table->id();

            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            $table->string('file_path')->nullable();

            $table->string('receipt_number')->unique();

            $table->timestamps();

        });
    }

    public function down():void
    {

        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');

    }

};
