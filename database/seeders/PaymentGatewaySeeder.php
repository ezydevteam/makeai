<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('payment-gateways', []) as $index => $gateway) {
            PaymentGateway::updateOrCreate(
                ['slug' => $index],
                [
                    'name' => $gateway['name'],
                    'description' => $gateway['description'] ?? null,
                    'sort_order' => array_search($index, array_keys(config('payment-gateways', [])), true) + 1,
                    'processing_fee_currency' => settings('pricing_currency_code', 'USD'),
                ]
            );
        }
    }
}
