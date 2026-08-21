<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('otp_codes')) {
            return;
        }

        /*
         * OTPs are intentionally short-lived. Invalidate every outstanding
         * credential instead of carrying plaintext values into the secure
         * schema as still-usable authentication secrets.
         */
        DB::table('otp_codes')->delete();

        if (
            Schema::hasColumn('otp_codes', 'code')
            && ! Schema::hasColumn('otp_codes', 'code_hash')
        ) {
            Schema::table('otp_codes', function (Blueprint $table): void {
                $table->renameColumn('code', 'code_hash');
            });
        } elseif (
            Schema::hasColumn('otp_codes', 'code')
            && Schema::hasColumn('otp_codes', 'code_hash')
        ) {
            Schema::table('otp_codes', function (Blueprint $table): void {
                $table->dropColumn('code');
            });
        }

        if (! Schema::hasColumn('otp_codes', 'code_hash')) {
            Schema::table('otp_codes', function (Blueprint $table): void {
                $table->string('code_hash')->nullable();
            });
        }

        /*
         * A renamed v2.0.6 fresh-install column may still be VARCHAR(8).
         * Expand it before production starts writing Laravel password hashes.
         */
        Schema::table('otp_codes', function (Blueprint $table): void {
            $table->string('code_hash')->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('otp_codes')) {
            DB::table('otp_codes')->delete();
        }

        /*
         * This security migration is intentionally one-way. Rolling it back
         * must never recreate a plaintext OTP credential column.
         */
    }
};
