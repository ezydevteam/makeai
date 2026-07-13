<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfNotExists('users', ['created_at']);
        $this->addIndexIfNotExists('users', ['subscription_status', 'subscription_ends_at']);
        $this->addIndexIfNotExists('users', ['is_active', 'created_at']);

        $this->addIndexIfNotExists('payments', ['status', 'created_at']);
        $this->addIndexIfNotExists('payments', ['status', 'type', 'created_at']);

        $this->addIndexIfNotExists('ai_usage_logs', ['created_at']);
        $this->addIndexIfNotExists('ai_usage_logs', ['user_id', 'created_at']);
        $this->addIndexIfNotExists('ai_usage_logs', ['tool_slug', 'created_at']);
        $this->addIndexIfNotExists('ai_usage_logs', ['provider', 'created_at']);
        $this->addIndexIfNotExists('ai_usage_logs', ['status', 'created_at']);

        $this->addIndexIfNotExists('newsletter_subscribers', ['created_at']);

        $this->addIndexIfNotExists('billing_subscriptions', ['cancelled_at']);
        $this->addIndexIfNotExists('billing_subscriptions', ['current_period_end']);
        $this->addIndexIfNotExists('billing_subscriptions', ['status', 'cancelled_at']);

        $this->addIndexIfNotExists('affiliate_referrals', ['landed_at']);
        $this->addIndexIfNotExists('affiliate_referrals', ['converted_at']);

        $this->addIndexIfNotExists('support_tickets', ['status', 'created_at']);

        $this->addIndexIfNotExists('comments', ['status', 'created_at']);

        $this->addIndexIfNotExists('login_history', ['success', 'created_at']);
        $this->addIndexIfNotExists('login_history', ['country', 'created_at']);
    }

    private function addIndexIfNotExists(string $table, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns) {
            $table->index($columns);
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('users', ['created_at']);
        $this->dropIndexIfExists('users', ['subscription_status', 'subscription_ends_at']);
        $this->dropIndexIfExists('users', ['is_active', 'created_at']);

        $this->dropIndexIfExists('payments', ['status', 'created_at']);
        $this->dropIndexIfExists('payments', ['status', 'type', 'created_at']);

        $this->dropIndexIfExists('ai_usage_logs', ['created_at']);
        $this->dropIndexIfExists('ai_usage_logs', ['user_id', 'created_at']);
        $this->dropIndexIfExists('ai_usage_logs', ['tool_slug', 'created_at']);
        $this->dropIndexIfExists('ai_usage_logs', ['provider', 'created_at']);
        $this->dropIndexIfExists('ai_usage_logs', ['status', 'created_at']);

        $this->dropIndexIfExists('newsletter_subscribers', ['created_at']);

        $this->dropIndexIfExists('gateway_subscriptions', ['cancelled_at']);
        $this->dropIndexIfExists('gateway_subscriptions', ['current_period_end']);
        $this->dropIndexIfExists('gateway_subscriptions', ['status', 'cancelled_at']);

        $this->dropIndexIfExists('affiliate_referrals', ['landed_at']);
        $this->dropIndexIfExists('affiliate_referrals', ['converted_at']);

        $this->dropIndexIfExists('support_tickets', ['status', 'created_at']);

        $this->dropIndexIfExists('comments', ['status', 'created_at']);

        $this->dropIndexIfExists('login_history', ['success', 'created_at']);
        $this->dropIndexIfExists('login_history', ['country', 'created_at']);
    }

    private function dropIndexIfExists(string $table, array $columns): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns) {
            $table->dropIndex($columns);
        });
    }

    /**
     * Driver-agnostic index check (replaces the MySQL-only `SHOW INDEX`, which
     * is a syntax error on SQLite and broke fresh migrates / the test suite).
     */
    private function indexExists(string $table, array $columns): bool
    {
        $indexName = $table.'_'.implode('_', $columns).'_index';

        return collect(Schema::getIndexes($table))
            ->contains(fn ($index) => ($index['name'] ?? null) === $indexName);
    }
};
