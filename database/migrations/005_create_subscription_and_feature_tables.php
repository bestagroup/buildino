<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('value_type', 20)->default('boolean');
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('building_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->json('limits')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['building_id', 'status']);
        });

        Schema::create('building_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['building_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_features');
        Schema::dropIfExists('building_subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('features');
    }
};
