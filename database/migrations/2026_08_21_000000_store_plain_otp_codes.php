<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('otp_codes', 'code_hash')
            && ! Schema::hasColumn('otp_codes', 'code')
        ) {
            Schema::table('otp_codes', function (Blueprint $table): void {
                $table->renameColumn('code_hash', 'code');
            });
        }

        if (
            Schema::hasColumn('otp_codes', 'code_hash')
            && Schema::hasColumn('otp_codes', 'code')
        ) {
            Schema::table('otp_codes', function (Blueprint $table): void {
                $table->dropColumn('code_hash');
            });
        }

        /*
         * Legacy rows contain one-way password hashes and cannot be converted
         * back to their original OTP. Codes are short-lived, so invalidating
         * them is safer than retaining rows that can never be verified.
         */
        DB::table('otp_codes')->delete();
    }

    public function down(): void
    {
        DB::table('otp_codes')->delete();

        if (
            Schema::hasColumn('otp_codes', 'code')
            && ! Schema::hasColumn('otp_codes', 'code_hash')
        ) {
            Schema::table('otp_codes', function (Blueprint $table): void {
                $table->renameColumn('code', 'code_hash');
            });
        }
    }
};
