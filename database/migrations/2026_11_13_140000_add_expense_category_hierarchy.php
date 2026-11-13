<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('financial_categories')->restrictOnDelete();
            $table->string('default_key', 100)->nullable();
            $table->char('title_key', 64)->nullable();
            $table->unique(['building_id', 'default_key'], 'financial_categories_default_unique');
            $table->unique(['building_id', 'type', 'title_key'], 'financial_categories_title_unique');
        });
    }

    public function down(): void
    {
        // Tenant copies and historical reference remapping are intentionally retained.
        // Reverting this feature requires a reviewed data migration, not a destructive rollback.
    }
};
