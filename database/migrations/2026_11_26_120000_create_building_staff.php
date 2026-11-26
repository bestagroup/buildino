<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->unique()->constrained('user_role_assignments')->restrictOnDelete();
            $table->string('employee_type', 40);
            $table->json('services');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['building_id', 'user_id']);
        });
        DB::table('permissions')->insertOrIgnore(['name' => 'building-staff.manage', 'display_name' => 'مدیریت کارمندان ساختمان', 'module' => 'users', 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('name', 'building-staff.manage')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['superadmin', 'building_manager'])->pluck('id') as $role) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('building_staff');
    }
};
