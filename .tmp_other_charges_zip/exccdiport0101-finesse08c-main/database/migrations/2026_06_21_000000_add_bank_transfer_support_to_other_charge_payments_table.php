<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPTION D — Bank Transfer Approval for Other Charges
 *
 * Adds the third payment method ('bank_transfer') to other_charge_payments,
 * plus the columns needed to support the proof-upload → DO-approval lifecycle:
 *
 *   payment_method enum: 'otc' | 'online' | 'bank_transfer'
 *
 *   Lifecycle for bank_transfer:
 *     awaiting_proof    → student submitted reference number, has not uploaded proof yet
 *     awaiting_approval → proof uploaded, awaiting Disbursing Officer review
 *     awaiting_proof    → (again) if DO rejects — rejection_reason is set, student re-uploads
 *     paid              → DO approved
 *     cancelled         → student self-cancelled while still in awaiting_proof
 *
 * payment_method enum modification uses raw DB::statement (matching the existing
 * convention in 2026_06_09_120000_add_awaiting_confirmation_to_other_charge_payments.php)
 * rather than Schema::table()->change() — doctrine/dbal's ->change() does not reliably
 * round-trip MySQL ENUM definitions, which is presumably why that migration uses raw SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE other_charge_payments
            MODIFY COLUMN payment_method ENUM('otc', 'online', 'bank_transfer') NOT NULL
        ");

        Schema::table('other_charge_payments', function (Blueprint $table) {
            // Proof of bank transfer — same disk/route-serving pattern as
            // Transaction::meta['proof_of_payment'], but as a real column since
            // other_charge_payments has no JSON meta column.
            $table->string('proof_path')->nullable()->after('reference');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_path');

            // Set by Accounting when rejecting a bank-transfer proof. Cleared
            // automatically when the student re-uploads (see OtherChargeService::submitProof).
            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('other_charge_payments', function (Blueprint $table) {
            $table->dropColumn(['proof_path', 'proof_uploaded_at', 'rejection_reason']);
        });

        // Revert any bank_transfer rows before shrinking the enum, or the
        // MODIFY COLUMN below will fail / silently null them out.
        DB::table('other_charge_payments')
            ->where('payment_method', 'bank_transfer')
            ->update(['payment_method' => 'otc']);

        DB::statement("
            ALTER TABLE other_charge_payments
            MODIFY COLUMN payment_method ENUM('otc', 'online') NOT NULL
        ");
    }
};
