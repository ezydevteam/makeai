<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->bigInteger('assigned_role_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->foreign('assigned_role_id')->references('id')->on('admin_roles')->onDelete('set null');
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('department_id')->unsigned();
            $table->bigInteger('assigned_to')->unsigned()->nullable();
            $table->string('subject', 500);
            $table->enum('status', ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('source', ['web', 'email', 'api'])->default('web');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_reply_at')->nullable()->index();
            $table->enum('last_reply_by', ['user', 'admin'])->nullable();
            $table->tinyInteger('satisfaction_rating')->unsigned()->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamp('user_last_read_at')->nullable();
            $table->timestamp('admin_last_read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('department_id')->references('id')->on('support_departments')->onDelete('restrict');
            $table->foreign('assigned_to')->references('id')->on('admins')->onDelete('set null');
            $table->index(['user_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['priority', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ticket_id')->unsigned();
            $table->enum('author_type', ['user', 'admin']);
            $table->bigInteger('author_id')->unsigned();
            $table->longText('content');
            $table->json('attachments')->nullable();
            $table->boolean('is_internal_note')->default(false);
            $table->boolean('is_ai_draft')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->index(['ticket_id', 'is_internal_note']);
            $table->index(['author_type', 'author_id']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ticket_id')->unsigned();
            $table->bigInteger('reply_id')->unsigned()->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size')->unsigned();
            $table->string('mime_type', 120);
            $table->enum('uploaded_by_type', ['user', 'admin']);
            $table->bigInteger('uploaded_by_id')->unsigned();
            $table->timestamp('created_at')->nullable();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->foreign('reply_id')->references('id')->on('support_ticket_replies')->onDelete('set null');
            $table->index(['ticket_id', 'reply_id']);
            $table->index(['uploaded_by_type', 'uploaded_by_id']);
        });

        Schema::create('support_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->bigInteger('department_id')->unsigned()->nullable();
            $table->bigInteger('created_by')->unsigned();
            $table->integer('usage_count')->unsigned()->default(0);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('support_departments')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('cascade');
            $table->index(['department_id', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_canned_responses');
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_departments');
    }
};
