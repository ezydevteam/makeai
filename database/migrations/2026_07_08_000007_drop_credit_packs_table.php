<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the dead `credit_packs` table. It was scaffolding for a fixed "credit pack"
 * purchase feature that was never built — the top-up system (CreditTopupController)
 * prices credits from settings (credit_price_per_unit + bonus tiers), never from this
 * table. No model, controller, route, seeder, or frontend ever queried it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('credit_packs');
    }

    public function down(): void
    {
        if (Schema::hasTable('credit_packs')) {
            return;
        }

        Schema::create('credit_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('credits', 12, 2);
            $table->decimal('price', 10, 2);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
