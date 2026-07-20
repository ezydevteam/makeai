<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per "Generate" run. Holds what was asked for, what actually landed, the
        // AI token spend, and the run status the History page polls.
        Schema::create('faker_ai_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();          // generator key, e.g. 'testimonials'
            $table->string('label', 120)->nullable();      // human label captured at run time
            $table->string('target', 191)->nullable();     // tool slug, post ulid, 'all', …
            $table->string('target_label', 191)->nullable();
            $table->unsignedInteger('requested_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->json('options')->nullable();
            $table->unsignedBigInteger('tokens_input')->default(0);
            $table->unsignedBigInteger('tokens_output')->default(0);
            $table->string('status', 20)->default('pending')->index(); // pending|processing|completed|failed
            $table->text('error')->nullable();
            // The admin who ran it. Nullable + nullOnDelete so removing an admin never orphans
            // or destroys the audit row.
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        // The reversal ledger: every mutation a batch made, recorded precisely enough to undo.
        //   action = 'delete'    → the row identified by subject_* was inserted; delete it.
        //   action = 'decrement' → column on subject_* was bumped by `amount`; subtract it back.
        // subject_id is a string so it holds both integer PKs and ULIDs.
        Schema::create('faker_ai_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('faker_ai_batches')->cascadeOnDelete();
            $table->string('action', 20);                 // delete | decrement
            $table->string('subject_type', 191);          // FQCN of the model
            $table->string('subject_id', 191);
            $table->string('column', 60)->nullable();     // for 'decrement'
            $table->unsignedInteger('amount')->nullable(); // for 'decrement'
            $table->timestamps();

            $table->index(['batch_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faker_ai_records');
        Schema::dropIfExists('faker_ai_batches');
    }
};
