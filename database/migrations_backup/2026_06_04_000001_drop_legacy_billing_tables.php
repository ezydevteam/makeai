<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('subscriptions', 'billing_subscriptions');
    }

    public function down(): void
    {
        Schema::rename('billing_subscriptions', 'subscriptions');
    }
};
