<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_charge_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('other_charge_id')
                ->constrained('other_charges')
                ->cascadeOnDelete();

            $table->foreignId('user_id')               // the student
                ->constrained('users')
                ->restrictOnDelete();

            // Payment details
            $table->decimal('amount_paid', 10, 2);     // must equal other_charges.amount (full pay only)

            $table->enum('payment_method', ['otc', 'online']);

            // OTC fields
            $table->string('or_number', 100)->nullable();   // official receipt number — required for OTC

            // Online / PayMongo fields
            $table->string('paymongo_session_id', 100)->nullable();
            $table->string('payment_intent_id', 100)->nullable();   // pi_xxx — used for webhook matching
            $table->string('reference', 100)->nullable();           // PAY-{pi_xxx}

            // Status lifecycle
            // pending          → online payment initiated, awaiting PayMongo confirmation
            // awaiting_proof   → bank transfer submitted, awaiting proof upload
            // awaiting_approval → proof uploaded or PayMongo paid, awaiting DO verification
            // paid             → OTC confirmed OR PayMongo webhook confirmed
            // failed           → PayMongo failure
            // cancelled        → student or admin cancelled
            $table->enum('status', [
                'pending',
                'awaiting_proof',
                'awaiting_approval',
                'paid',
                'failed',
                'cancelled',
            ])->default('pending');

            // Who recorded this payment (null = student self-paid online)
            $table->foreignId('collected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Enforce one paid record per student per charge
            $table->unique(['other_charge_id', 'user_id', 'status'], 'unique_paid_per_student_charge');

            $table->index(['other_charge_id', 'user_id']);
            $table->index('payment_intent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_charge_payments');
    }
};
