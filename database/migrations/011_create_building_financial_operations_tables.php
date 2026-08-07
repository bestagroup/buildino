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
        | Building Expenses
        | هزینه های ساختمان
        |--------------------------------------------------------------------------
        */

        Schema::create('building_expenses', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            $table->integer('amount');

            $table->date('expense_date');

            $table->string('invoice_number')->nullable();

            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['building_id', 'expense_date']);

        });
        /*
        |--------------------------------------------------------------------------
        | Building Incomes
        | درآمدهای ساختمان
        |--------------------------------------------------------------------------
        */

        Schema::create('building_incomes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('financial_category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            $table->integer('amount');

            $table->date('income_date');

            $table->string('reference_number')->nullable();

            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['building_id', 'income_date']);

        });
        /*
        |--------------------------------------------------------------------------
        | Financial Documents
        |--------------------------------------------------------------------------
        */

        Schema::create('financial_documents', function (Blueprint $table) {

            $table->id();

            $table->foreignId('building_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->enum('type', ['expense','income','invoice','receipt','contract','other']);

            $table->string('title');

            $table->string('file_name');

            $table->string('file_path');

            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('financial_documents');
        Schema::dropIfExists('building_incomes');
        Schema::dropIfExists('building_expenses');
    }
};
