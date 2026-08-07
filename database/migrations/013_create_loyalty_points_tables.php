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
        | Loyalty Point Accounts
        |--------------------------------------------------------------------------
        */
        Schema::create('loyalty_accounts', function(Blueprint $table){

            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->integer('balance')->default(0);

            $table->timestamps();

            $table->unique('user_id');
        });
        /*
        |--------------------------------------------------------------------------
        | Loyalty Point Transactions
        |--------------------------------------------------------------------------
        */

        Schema::create('loyalty_transactions', function(Blueprint $table){

            $table->id();

            $table->foreignId('loyalty_account_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->enum('type',['earn','spend','expire','adjust']);

            $table->integer('points');

            $table->string('reference_type')->nullable();

            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
        /*
        |--------------------------------------------------------------------------
        | Loyalty Rewards
        |--------------------------------------------------------------------------
        */
        Schema::create('loyalty_rewards', function(Blueprint $table){

            $table->id();

            $table->string('title');

            $table->text('description')->nullable();

            $table->integer('required_points');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
        /*
        |--------------------------------------------------------------------------
        | User Reward Claims
        |--------------------------------------------------------------------------
        */

        Schema::create('loyalty_reward_claims', function(Blueprint $table){

            $table->id();

            $table->foreignId('loyalty_reward_id')->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('claimed_at')->nullable();

            $table->enum('status',['pending','approved','rejected'])->default('pending');

            $table->timestamps();

        });
    }
    public function down(): void
    {
        Schema::dropIfExists('loyalty_reward_claims');
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');

    }

};
