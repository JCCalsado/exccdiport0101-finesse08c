<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ENUM modification requires a full MODIFY COLUMN — Blueprint::enum()
        // cannot add values to an existing ENUM. Raw SQL is the only safe path.
        //
        // Order of values matches insertion order in the original migration:
        // admin, accounting, registrar, student
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'accounting', 'registrar', 'student')
            NOT NULL DEFAULT 'student'
        ");
    }

    public function down(): void
    {
        // Revert: remove 'registrar'. Any registrar rows must be reassigned first
        // or this will fail with a data truncation error.
        DB::statement("
            UPDATE users SET role = 'admin' WHERE role = 'registrar'
        ");

        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('admin', 'accounting', 'student')
            NOT NULL DEFAULT 'student'
        ");
    }
};