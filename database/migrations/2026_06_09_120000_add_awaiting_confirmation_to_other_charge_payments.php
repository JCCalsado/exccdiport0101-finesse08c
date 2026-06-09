<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'awaiting_confirmation' to other_charge_payments.status enum.
     *
     * Status lifecycle:
     *   pending                → checkout session created, student not yet paid
     *   awaiting_confirmation  → student returned from PayMongo success URL; webhook not yet fired
     *   paid                   → webhook confirmed (authoritative)
     *   failed                 → PayMongo rejected
     *   cancelled              → abandoned / crash orphan
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE other_charge_payments
            MODIFY COLUMN status ENUM(
                'pending',
                'awaiting_confirmation',
                'awaiting_proof',
                'awaiting_approval',
                'paid',
                'failed',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        // First update any awaiting_confirmation rows so down() doesn't fail the constraint
        DB::table('other_charge_payments')
            ->where('status', 'awaiting_confirmation')
            ->update(['status' => 'pending']);

        DB::statement("
            ALTER TABLE other_charge_payments
            MODIFY COLUMN status ENUM(
                'pending',
                'awaiting_proof',
                'awaiting_approval',
                'paid',
                'failed',
                'cancelled'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
