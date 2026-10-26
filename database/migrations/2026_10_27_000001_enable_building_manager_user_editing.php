<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('name', 'building_manager')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'users.update')->value('id');

        // Fresh installations receive this permission from RoleMatrixSeeder.
        if ($roleId === null || $permissionId === null) {
            return;
        }

        DB::table('permission_role')->insertOrIgnore([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve grants that may have existed before this additive migration.
        // Access revocation must be an explicit role-management decision.
    }
};
