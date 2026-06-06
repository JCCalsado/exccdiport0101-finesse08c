<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Accounting sub-role type. Only populated when role = 'accounting'.
            // NULL for admin, student, and registrar role users.
            $table->enum('accounting_type', ['cashier', 'bookkeeper', 'disbursing_officer'])
                  ->nullable()
                  ->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accounting_type');
        });
    }
};