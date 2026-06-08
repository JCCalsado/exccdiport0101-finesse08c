<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_charges', function (Blueprint $table) {
            $table->id();

            $table->string('title');                   // e.g. "Christmas Fee 2025-2026"
            $table->text('description')->nullable();

            // Amount is stored in pesos. Full payment only — no partial.
            $table->decimal('amount', 10, 2);

            // Targeting filters — null = no restriction on that dimension
            $table->string('school_year', 50);             // e.g. "2025-2026" — REQUIRED always
            $table->string('semester', 50)->nullable();    // e.g. "1st Sem" — null = all semesters
            $table->string('year_level', 50)->nullable();  // e.g. "1st Year" — null = all year levels
            $table->string('course', 50)->nullable();      // e.g. "BSEET" — null = all courses

            // Lifecycle
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();   // null = draft
            $table->timestamp('updated_after_publish_at')->nullable(); // set when edited after publish

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for dynamic scope queries
            $table->index(['school_year', 'semester', 'year_level', 'course'], 'other_charges_filter_idx');
            $table->index('published_at');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_charges');
    }
};
