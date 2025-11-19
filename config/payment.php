<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Payment gateway settings for processing online payments
    |
    */

    // General payment settings
    'min_amount' => env('PAYMENT_MIN_AMOUNT', 1.00),
    'max_amount' => env('PAYMENT_MAX_AMOUNT', 100000.00),
    'currency' => env('PAYMENT_CURRENCY', 'PHP'),

    // Gateway endpoints and credentials
    'credentials' => [
        'gcash' => [
            'api_key' => env('GCASH_API_KEY'),
            'api_secret' => env('GCASH_API_SECRET'),
            'merchant_id' => env('GCASH_MERCHANT_ID'),
            'webhook_secret' => env('GCASH_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    'endpoints' => [
        'gcash' => [
            'qr_generate' => env('GCASH_QR_GENERATE_URL', 'https://api.gcash.com/v1/qr/generate'),
            'payment_status' => env('GCASH_PAYMENT_STATUS_URL', 'https://api.gcash.com/v1/payment/status'),
        ],
        'paypal' => [
            'get_token' => env('PAYPAL_GET_TOKEN_URL', 'https://api-m.sandbox.paypal.com/v1/oauth2/token'),
            'create_order' => env('PAYPAL_CREATE_ORDER_URL', 'https://api-m.sandbox.paypal.com/v2/checkout/orders'),
            'capture_order' => env('PAYPAL_CAPTURE_ORDER_URL', 'https://api-m.sandbox.paypal.com/v2/checkout/orders'),
        ],
        'stripe' => [
            'create_session' => env('STRIPE_CREATE_SESSION_URL', 'https://api.stripe.com/v1/checkout/sessions'),
            'retrieve_session' => env('STRIPE_RETRIEVE_SESSION_URL', 'https://api.stripe.com/v1/checkout/sessions'),
        ],
    ],

    // Gateway-specific settings
    'gcash' => [
        'expiry_seconds' => env('GCASH_QR_EXPIRY_SECONDS', 900), // 15 minutes
        'fees' => [
            'fixed' => env('GCASH_FIXED_FEE', 0),
            'percentage' => env('GCASH_PERCENTAGE_FEE', 2.5), // 2.5%
        ],
    ],

    'paypal' => [
        'expiry_hours' => env('PAYPAL_ORDER_EXPIRY_HOURS', 2),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
        'fees' => [
            'fixed' => env('PAYPAL_FIXED_FEE', 0.30), // $0.30 fixed
            'percentage' => env('PAYPAL_PERCENTAGE_FEE', 4.4), // 4.4% for Philippines
        ],
    ],

    'stripe' => [
        'expiry_hours' => env('STRIPE_SESSION_EXPIRY_HOURS', 24),
        'currency' => env('STRIPE_CURRENCY', 'php'),
        'fees' => [
            'fixed' => env('STRIPE_FIXED_FEE', 2.50), // ₱2.50 fixed
            'percentage' => env('STRIPE_PERCENTAGE_FEE', 3.0), // 3% for Philippines
        ],
    ],

    // Security settings
    'security' => [
        'rate_limit_attempts' => env('PAYMENT_RATE_LIMIT_ATTEMPTS', 5),
        'rate_limit_minutes' => env('PAYMENT_RATE_LIMIT_MINUTES', 1),
        'max_failed_attempts' => env('PAYMENT_MAX_FAILED_ATTEMPTS', 10),
        'failed_attempts_hours' => env('PAYMENT_FAILED_ATTEMPTS_HOURS', 1),
    ],

    // Webhook settings
    'webhooks' => [
        'timeout' => env('PAYMENT_WEBHOOK_TIMEOUT', 30),
        'retry_attempts' => env('PAYMENT_WEBHOOK_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('PAYMENT_WEBHOOK_RETRY_DELAY', 5), // seconds
    ],

    // Notification settings
    'notifications' => [
        'payment_confirmation' => env('SEND_PAYMENT_CONFIRMATION_EMAIL', true),
        'payment_failure' => env('SEND_PAYMENT_FAILURE_EMAIL', true),
        'admin_notifications' => env('SEND_ADMIN_PAYMENT_NOTIFICATIONS', true),
    ],

    // Receipt settings
    'receipts' => [
        'auto_generate' => env('AUTO_GENERATE_RECEIPTS', true),
        'template' => env('RECEIPT_TEMPLATE', 'default'),
        'logo_path' => env('RECEIPT_LOGO_PATH', 'images/logo.png'),
        'footer_text' => env('RECEIPT_FOOTER_TEXT', 'Thank you for your payment!'),
    ],

    // Email settings
    'emails' => [
        'payment_confirmation' => [
            'subject' => env('PAYMENT_CONFIRMATION_SUBJECT', 'Payment Confirmation - {{school_name}}'),
            'template' => 'emails.payment.confirmation',
        ],
        'payment_failure' => [
            'subject' => env('PAYMENT_FAILURE_SUBJECT', 'Payment Failed - {{school_name}}'),
            'template' => 'emails.payment.failure',
        ],
    ],

    // Report settings
    'reports' => [
        'daily_reconciliation' => env('ENABLE_DAILY_RECONCILIATION', true),
        'reconciliation_time' => env('DAILY_RECONCILIATION_TIME', '23:59'),
        'export_formats' => explode(',', env('REPORT_EXPORT_FORMATS', 'csv,xlsx,pdf')),
    ],

    // Logging settings
    'logging' => [
        'log_webhooks' => env('LOG_PAYMENT_WEBHOOKS', true),
        'log_failed_attempts' => env('LOG_FAILED_PAYMENT_ATTEMPTS', true),
        'log_webhook_responses' => env('LOG_WEBHOOK_RESPONSES', true),
    ],
];