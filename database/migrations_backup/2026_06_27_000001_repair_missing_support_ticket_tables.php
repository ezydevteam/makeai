<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_departments')) {
            Schema::create('support_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('email')->nullable();
                $table->foreignId('assigned_role_id')->nullable()->constrained('admin_roles')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number', 20)->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('support_departments')->restrictOnDelete();
                $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('subject', 500);
                $table->enum('status', ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])->default('open');
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
                $table->enum('source', ['web', 'email', 'api'])->default('web');
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('last_reply_at')->nullable();
                $table->enum('last_reply_by', ['user', 'admin'])->nullable();
                $table->unsignedTinyInteger('satisfaction_rating')->nullable();
                $table->text('satisfaction_comment')->nullable();
                $table->timestamp('user_last_read_at')->nullable();
                $table->timestamp('admin_last_read_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'status']);
                $table->index(['department_id', 'status']);
                $table->index(['assigned_to', 'status']);
                $table->index(['priority', 'status']);
                $table->index('last_reply_at');
            });
        } elseif (! Schema::hasColumn('support_tickets', 'deleted_at')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('support_ticket_replies')) {
            Schema::create('support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->enum('author_type', ['user', 'admin']);
                $table->unsignedBigInteger('author_id');
                $table->longText('content');
                $table->json('attachments')->nullable();
                $table->boolean('is_internal_note')->default(false);
                $table->boolean('is_ai_draft')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['ticket_id', 'is_internal_note']);
                $table->index(['author_type', 'author_id']);
            });
        }

        if (! Schema::hasTable('support_ticket_attachments')) {
            Schema::create('support_ticket_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('reply_id')->nullable()->constrained('support_ticket_replies')->nullOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size');
                $table->string('mime_type', 120);
                $table->enum('uploaded_by_type', ['user', 'admin']);
                $table->unsignedBigInteger('uploaded_by_id');
                $table->timestamp('created_at')->nullable();

                $table->index(['ticket_id', 'reply_id']);
                $table->index(['uploaded_by_type', 'uploaded_by_id']);
            });
        }

        if (! Schema::hasTable('support_canned_responses')) {
            Schema::create('support_canned_responses', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->longText('content');
                $table->foreignId('department_id')->nullable()->constrained('support_departments')->nullOnDelete();
                $table->foreignId('created_by')->constrained('admins')->cascadeOnDelete();
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();

                $table->index(['department_id', 'title']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_canned_responses');
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_departments');
    }
};
