<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_sms_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('charge_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['charge_period_id', 'unit_id']);
        });
        Schema::create('charge_sms_notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('building_id')->constrained()->restrictOnDelete();
            $t->foreignId('charge_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('mobile', 20);
            $t->string('currency', 3);
            $t->unsignedBigInteger('amount');
            $t->json('invoice_ids');
            $t->json('parameters');
            $t->string('template');
            $t->string('status', 30);
            $t->string('provider_message_id')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['charge_period_id', 'user_id']);
        });
        DB::table('permissions')->insertOrIgnore(['name' => 'charge-sms.manage', 'display_name' => 'ارسال پیامک شارژ دوره', 'module' => 'notifications', 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('name', 'charge-sms.manage')->value('id');
        foreach (DB::table('roles')->whereIn('name', ['superadmin', 'building_manager'])->pluck('id') as $role) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_sms_notices');
        Schema::dropIfExists('charge_sms_recipients');
    }
};
