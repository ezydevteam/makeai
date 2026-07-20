<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Currencies table — multi-currency support with exchange rates.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();     // USD, BDT, EUR
            $table->string('symbol', 10);              // $, ৳, €
            $table->string('name', 100);               // US Dollar
            $table->decimal('exchange_rate', 12, 6)->default(1.000000); // relative to USD
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
