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
        | Funds
        |--------------------------------------------------------------------------
        */

        Schema::create('funds', function(Blueprint $table){

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('title');

            $table->integer('balance')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
        /*
        |--------------------------------------------------------------------------
        | Financial Categories
        |--------------------------------------------------------------------------
        */

        Schema::create('financial_categories', function(Blueprint $table){

            $table->id();

            $table->string('title');

            $table->enum('type',['income', 'expense', 'charge']);

            $table->timestamps();

        });
        /*
        |--------------------------------------------------------------------------
        | Charge Formulas
        |--------------------------------------------------------------------------
        */

        Schema::create('charge_formulas', function(Blueprint $table){

            $table->id();

            $table->string('title');

            $table->enum('calculation_type',['equal', 'area', 'persons', 'custom']);

            $table->json('configuration')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
        /*
        |--------------------------------------------------------------------------
        | Charge Items
        |--------------------------------------------------------------------------
        */

        Schema::create('charge_items', function(Blueprint $table){

            $table->id();

            $table->foreignId('charge_formula_id')->constrained()->cascadeOnDelete();

            $table->foreignId('financial_category_id')->constrained()->cascadeOnDelete();

            $table->string('title');

            $table->integer('amount');

            $table->timestamps();

        });


    }

    public function down(): void
    {
        Schema::dropIfExists('charge_items');
        Schema::dropIfExists('charge_formulas');
        Schema::dropIfExists('financial_categories');
        Schema::dropIfExists('funds');
    }
};
