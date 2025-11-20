<?php
// config/payment.php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment General Settings
    |--------------------------------------------------------------------------
    */
    'min_amount' => env('PAYMENT_MIN_AMOUNT', 1.00),
    'max_amount' => env('PAYMENT_MAX_AMOUNT', 100000.00),
    'currency' => env('PAYMENT_CURRENCY', 'PHP'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Credentials
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Gateway Endpoints
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Gateway-Specific Settings
    |--------------------------------------------------------------------------
    */
    'gcash' => [
        'expiry_seconds' => env('GCASH_QR_EXPIRY_SECONDS', 900),
        'fees' => [
            'fixed' => env('GCASH_FIXED_FEE', 0),
            'percentage' => env('GCASH_PERCENTAGE_FEE', 2.5),
        ],
    ],

    'paypal' => [
        'expiry_hours' => env('PAYPAL_ORDER_EXPIRY_HOURS', 2),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
        'fees' => [
            'fixed' => env('PAYPAL_FIXED_FEE', 0.30),
            'percentage' => env('PAYPAL_PERCENTAGE_FEE', 4.4),
        ],
    ],

    'stripe' => [
        'expiry_hours' => env('STRIPE_SESSION_EXPIRY_HOURS', 24),
        'currency' => env('STRIPE_CURRENCY', 'php'),
        'fees' => [
            'fixed' => env('STRIPE_FIXED_FEE', 2.50),
            'percentage' => env('STRIPE_PERCENTAGE_FEE', 3.0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'rate_limit_attempts' => env('PAYMENT_RATE_LIMIT_ATTEMPTS', 5),
        'rate_limit_minutes' => env('PAYMENT_RATE_LIMIT_MINUTES', 1),
        'max_failed_attempts' => env('PAYMENT_MAX_FAILED_ATTEMPTS', 10),
        'failed_attempts_hours' => env('PAYMENT_FAILED_ATTEMPTS_HOURS', 1),
        'max_request_size' => env('PAYMENT_MAX_REQUEST_SIZE', 1024 * 1024), // 1MB
        'min_request_interval' => env('PAYMENT_MIN_REQUEST_INTERVAL', 2), // seconds
        'allowed_origins' => explode(',', env('PAYMENT_ALLOWED_ORIGINS', config('app.url'))),
        'ip_whitelist' => explode(',', env('PAYMENT_IP_WHITELIST', '')),
        'ip_blacklist' => explode(',', env('PAYMENT_IP_BLACKLIST', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Settings
    |--------------------------------------------------------------------------
    */
    'fraud' => [
        'enabled' => env('FRAUD_DETECTION_ENABLED', true),
        'threshold' => env('FRAUD_DETECTION_THRESHOLD', 50), // Score threshold
        'failed_attempts_window' => env('FRAUD_FAILED_ATTEMPTS_WINDOW', 60), // minutes
        'max_failed_attempts' => env('FRAUD_MAX_FAILED_ATTEMPTS', 5),
        'max_travel_speed' => env('FRAUD_MAX_TRAVEL_SPEED', 1000), // km/h
        'max_payments_hour' => env('FRAUD_MAX_PAYMENTS_HOUR', 5),
        'max_payments_day' => env('FRAUD_MAX_PAYMENTS_DAY', 15),
        'max_payments_week' => env('FRAUD_MAX_PAYMENTS_WEEK', 50),
        'alert_emails' => explode(',', env('FRAUD_ALERT_EMAILS', 'admin@ccdi.edu.ph')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'payment_confirmation' => env('SEND_PAYMENT_CONFIRMATION_EMAIL', true),
        'payment_failure' => env('SEND_PAYMENT_FAILURE_EMAIL', true),
        'admin_notifications' => env('SEND_ADMIN_PAYMENT_NOTIFICATIONS', true),
        'admin_emails' => explode(',', env('ADMIN_NOTIFICATION_EMAILS', 'admin@ccdi.edu.ph,accounting@ccdi.edu.ph')),
        'sms_confirmation' => env('SEND_SMS_CONFIRMATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'timeout' => env('PAYMENT_WEBHOOK_TIMEOUT', 30),
        'retry_attempts' => env('PAYMENT_WEBHOOK_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('PAYMENT_WEBHOOK_RETRY_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt Settings
    |--------------------------------------------------------------------------
    */
    'receipts' => [
        'auto_generate' => env('AUTO_GENERATE_RECEIPTS', true),
        'template' => env('RECEIPT_TEMPLATE', 'default'),
        'logo_path' => env('RECEIPT_LOGO_PATH', 'images/logo.png'),
        'footer_text' => env('RECEIPT_FOOTER_TEXT', 'Thank you for your payment!'),
    ],
];