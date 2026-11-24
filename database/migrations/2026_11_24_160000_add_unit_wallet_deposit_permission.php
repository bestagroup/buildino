<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(['name' => 'unit-wallet-deposits.create'], [
            'display_name' => 'ثبت واریزی مستند به کیف پول واحد', 'module' => 'wallets', 'updated_at' => now(),
        ]);
        $permission = DB::table('permissions')->where('name', 'unit-wallet-deposits.create')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['superadmin', 'building_manager', 'finance_manager'])->pluck('id') as $role) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('name', 'unit-wallet-deposits.create')->value('id');
        DB::table('permission_role')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
    }
};
