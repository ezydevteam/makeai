<?php

return [
    'stripe' => [
        'name' => 'Stripe',
        'description' => 'Cards, wallets, and recurring subscriptions.',
        'webhook_path' => '/webhooks/stripe',
        'fields' => [
            ['key' => 'publishable_key', 'label' => 'Publishable Key', 'type' => 'text'],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password'],
        ],
    ],
    'paypal' => [
        'name' => 'PayPal',
        'description' => 'PayPal checkout and subscriptions.',
        'webhook_path' => '/webhooks/paypal',
        'fields' => [
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password'],
            ['key' => 'webhook_id', 'label' => 'Webhook ID', 'type' => 'text'],
        ],
    ],
    'paddle' => [
        'name' => 'Paddle',
        'description' => 'Merchant of record payments and VAT handling.',
        'webhook_path' => '/webhooks/paddle',
        'fields' => [
            ['key' => 'vendor_id', 'label' => 'Vendor ID', 'type' => 'text'],
            ['key' => 'api_key', 'label' => 'API Key / Auth Code', 'type' => 'password'],
            ['key' => 'public_key', 'label' => 'Public Key', 'type' => 'textarea'],
        ],
    ],
    'razorpay' => [
        'name' => 'Razorpay',
        'description' => 'Cards, UPI, and India-focused checkout.',
        'webhook_path' => '/webhooks/razorpay',
        'fields' => [
            ['key' => 'key_id', 'label' => 'Key ID', 'type' => 'text'],
            ['key' => 'key_secret', 'label' => 'Key Secret', 'type' => 'password'],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password'],
        ],
    ],
    'sslcommerz' => [
        'name' => 'SSLCommerz',
        'description' => 'Bangladesh payment gateway.',
        'webhook_path' => '/webhooks/sslcommerz',
        'fields' => [
            ['key' => 'store_id', 'label' => 'Store ID', 'type' => 'text'],
            ['key' => 'store_password', 'label' => 'Store Password', 'type' => 'password'],
        ],
    ],
    'coingate' => [
        'name' => 'CoinGate',
        'description' => 'Crypto payments.',
        'webhook_path' => '/webhooks/coingate',
        'fields' => [
            ['key' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password'],
            ['key' => 'webhook_token', 'label' => 'Webhook Token', 'type' => 'password'],
        ],
    ],
    'paystack' => [
        'name' => 'Paystack',
        'description' => 'Africa-focused cards and bank payments.',
        'webhook_path' => '/webhooks/paystack',
        'fields' => [
            ['key' => 'public_key', 'label' => 'Public Key', 'type' => 'text'],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
        ],
    ],
    'bank_transfer' => [
        'name' => 'Bank Transfer',
        'description' => 'Manual bank transfer with admin approval.',
        'fields' => [
            ['key' => 'instructions', 'label' => 'Payment Instructions', 'type' => 'textarea'],
        ],
    ],
    '2checkout' => [
        'name' => '2Checkout',
        'description' => '2Checkout hosted checkout payments.',
        'webhook_path' => '/webhooks/2checkout',
        'fields' => [
            ['key' => 'merchant_code', 'label' => 'Merchant Code', 'type' => 'text'],
            ['key' => 'secret_key', 'label' => 'Secret Word / Secret Key', 'type' => 'password'],
        ],
    ],
];
