<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep IDs and all historical references intact; do not merge same-name categories.
        DB::table('financial_categories')->where('type', 'expense')
            ->whereNotNull('building_id')->update(['building_id' => null]);
    }

    public function down(): void
    {
        // Global categories may now be used by many buildings; ownership cannot be inferred safely.
    }
};
