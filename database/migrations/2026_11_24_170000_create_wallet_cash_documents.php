<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_cash_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_transfer_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('direction', 10);
            $table->unsignedBigInteger('amount');
            $table->string('document_number', 100);
            $table->text('description')->nullable();
            $table->foreignId('file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['building_id', 'direction', 'document_number'], 'wallet_cash_document_reference_unique');
        });
        DB::table('permissions')->updateOrInsert(['name' => 'wallet-cash.manage'], ['display_name' => 'ثبت دریافت و پرداخت کیف پول', 'module' => 'wallets', 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('name', 'wallet-cash.manage')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['superadmin', 'building_manager', 'finance_manager'])->pluck('id') as $role) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_cash_documents');
        $permission = DB::table('permissions')->where('name', 'wallet-cash.manage')->value('id');
        DB::table('permission_role')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
    }
};
