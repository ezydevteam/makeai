<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aip_jobs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            // Nullable: guest operations are allowed when the admin sets an
            // operation's access level to `guest`.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('guest_ip', 45)->nullable();

            $table->string('operation', 40);
            $table->string('tier', 20);                 // generate | provider | local
            $table->string('status', 20)->default('queued'); // queued|processing|completed|failed
            $table->string('engine', 40);               // openai | stability | replicate | gd | …
            $table->string('model', 100)->nullable();   // AiModel slug, generation only

            $table->unsignedBigInteger('input_asset_id')->nullable();
            $table->string('mask_path', 500)->nullable();
            $table->string('reference_path', 500)->nullable();

            $table->json('params')->nullable();
            $table->unsignedTinyInteger('batch_size')->default(1);

            $table->decimal('credits_charged', 12, 4)->default(0);
            $table->string('billing_mode', 10)->default('free'); // media|flat|free
            $table->boolean('refunded')->default(false);

            $table->string('provider_job_id', 191)->nullable(); // e.g. Replicate prediction id
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('operation');
            $table->index(['guest_ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aip_jobs');
    }
};
