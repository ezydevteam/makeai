<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'processing', 'ready', 'downloaded', 'expired'])->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cookie_consent')) {
                $table->json('cookie_consent')->nullable()->after('preferences');
            }
            if (! Schema::hasColumn('users', 'allow_data_improve')) {
                $table->boolean('allow_data_improve')->default(true)->after('cookie_consent');
            }
            if (! Schema::hasColumn('users', 'scheduled_deletion_at')) {
                $table->timestamp('scheduled_deletion_at')->nullable()->after('allow_data_improve');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cookie_consent', 'allow_data_improve', 'scheduled_deletion_at']);
        });
        Schema::dropIfExists('data_export_requests');
    }
};
