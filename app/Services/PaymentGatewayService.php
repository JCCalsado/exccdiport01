<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\Payment;
use App\Models\Student;

class PaymentGatewayService
{
    /**
     * Supported payment gateways
     */
    const GATEWAY_GCASH = 'gcash';
    const GATEWAY_PAYPAL = 'paypal';
    const GATEWAY_STRIPE = 'stripe';

    /**
     * Payment gateway endpoints
     */
    private array $endpoints;

    /**
     * Payment gateway credentials
     */
    private array $credentials;

    public function __construct()
    {
        $this->endpoints = config('payment.endpoints', []);
        $this->credentials = config('payment.credentials', []);
    }

    /**
     * Initiate payment with specified gateway
     */
    public function initiatePayment(array $data): array
    {
        $gateway = $data['payment_method'] ?? 'gcash';

        // Validate payment amount and student
        $this->validatePaymentRequest($data);

        // Create payment record
        $payment = $this->createPaymentRecord($data);

        try {
            $result = match ($gateway) {
                self::GATEWAY_GCASH => $this->initiateGCashPayment($payment, $data),
                self::GATEWAY_PAYPAL => $this->initiatePayPalPayment($payment, $data),
                self::GATEWAY_STRIPE => $this->initiateStripePayment($payment, $data),
                default => throw new \Exception("Unsupported payment gateway: {$gateway}")
            };

            // Store gateway-specific details
            $this->storeGatewayDetails($payment, $gateway, $result);

            return [
                'success' => true,
                'payment_id' => $payment->id,
                'redirect_url' => $result['redirect_url'] ?? null,
                'qr_code' => $result['qr_code'] ?? null,
                'gateway' => $gateway,
                'message' => 'Payment initiated successfully'
            ];

        } catch (\Exception $e) {
            Log::error("Payment initiation failed: " . $e->getMessage(), [
                'payment_id' => $payment->id ?? null,
                'gateway' => $gateway,
                'data' => $data
            ]);

            // Update payment status to failed if payment record exists
            if (isset($payment)) {
                $payment->update(['status' => Payment::STATUS_FAILED]);
            }

            throw new \Exception("Payment initiation failed: " . $e->getMessage());
        }
    }

    /**
     * Initiate GCash payment (QR Code generation)
     */
    private function initiateGCashPayment(Payment $payment, array $data): array
    {
        $endpoint = $this->endpoints['gcash']['qr_generate'] ?? null;
        $credentials = $this->credentials['gcash'] ?? [];

        if (!$endpoint || !isset($credentials['api_key'], $credentials['api_secret'])) {
            throw new \Exception('GCash gateway not properly configured');
        }

        $payload = [
            'amount' => $payment->amount,
            'description' => $payment->description,
            'merchant_order_id' => 'PAYMENT_' . $payment->id,
            'expiry_seconds' => config('payment.gcash.expiry_seconds', 900), // 15 minutes
            'callback_url' => route('payments.webhook.gcash'),
            'success_url' => route('payment.success', $payment->id),
            'fail_url' => route('payment.failed', $payment->id),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credentials['api_key'],
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new \Exception('GCash QR code generation failed: ' . $response->body());
        }

        $result = $response->json();

        return [
            'gateway_transaction_id' => $result['data']['qr_id'] ?? null,
            'qr_code' => $result['data']['qr_code'] ?? null,
            'redirect_url' => null, // GCash uses QR code
            'expiry_time' => now()->addSeconds($payload['expiry_seconds']),
        ];
    }

    /**
     * Initiate PayPal payment
     */
    private function initiatePayPalPayment(Payment $payment, array $data): array
    {
        $endpoint = $this->endpoints['paypal']['create_order'] ?? null;
        $credentials = $this->credentials['paypal'] ?? [];

        if (!$endpoint || !isset($credentials['client_id'], $credentials['client_secret'])) {
            throw new \Exception('PayPal gateway not properly configured');
        }

        // Get PayPal access token
        $accessToken = $this->getPayPalAccessToken();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'PAYMENT_' . $payment->id,
                    'description' => $payment->description,
                    'amount' => [
                        'currency_code' => config('payment.paypal.currency', 'USD'),
                        'value' => (string) $payment->amount,
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => route('payment.success', $payment->id),
                'cancel_url' => route('payment.cancelled', $payment->id),
                'brand_name' => config('app.name', 'School Payment Portal'),
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new \Exception('PayPal order creation failed: ' . $response->body());
        }

        $result = $response->json();

        // Extract approval URL for redirect
        $approveLink = collect($result['links'] ?? [])
            ->firstWhere('rel', 'approve');

        if (!$approveLink) {
            throw new \Exception('PayPal approval link not found');
        }

        return [
            'gateway_transaction_id' => $result['id'] ?? null,
            'redirect_url' => $approveLink['href'],
            'qr_code' => null,
            'expiry_time' => now()->addHours(config('payment.paypal.expiry_hours', 2)),
        ];
    }

    /**
     * Initiate Stripe payment
     */
    private function initiateStripePayment(Payment $payment, array $data): array
    {
        $endpoint = $this->endpoints['stripe']['create_session'] ?? null;
        $credentials = $this->credentials['stripe'] ?? [];

        if (!$endpoint || !isset($credentials['secret_key'])) {
            throw new \Exception('Stripe gateway not properly configured');
        }

        $payload = [
            'payment_method_types' => ['card', 'alipay'],
            'mode' => 'payment',
            'success_url' => route('payments.success', $payment->id) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payments.cancelled', $payment->id),
            'client_reference_id' => 'PAYMENT_' . $payment->id,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => config('payment.stripe.currency', 'usd'),
                        'product_data' => [
                            'name' => $payment->description,
                            'metadata' => [
                                'payment_id' => $payment->id,
                            ],
                        ],
                        'unit_amount' => (int) ($payment->amount * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ],
            ],
            'expires_at' => now()->addHours(config('payment.stripe.expiry_hours', 24))->timestamp,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $credentials['secret_key'],
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new \Exception('Stripe session creation failed: ' . $response->body());
        }

        $result = $response->json();

        return [
            'gateway_transaction_id' => $result['id'] ?? null,
            'redirect_url' => $result['url'] ?? null,
            'qr_code' => null,
            'expiry_time' => now()->addSeconds($result['expires_at'] - time()),
        ];
    }

    /**
     * Get PayPal access token
     */
    private function getPayPalAccessToken(): string
    {
        $cacheKey = 'paypal_access_token';

        return Cache::remember($cacheKey, 3000, function () { // Cache for 50 minutes
            $endpoint = $this->endpoints['paypal']['get_token'] ?? null;
            $credentials = $this->credentials['paypal'] ?? [];

            $response = Http::withBasicAuth(
                $credentials['client_id'],
                $credentials['client_secret']
            )->asForm()->post($endpoint, [
                'grant_type' => 'client_credentials'
            ]);

            if (!$response->successful()) {
                throw new \Exception('PayPal token generation failed: ' . $response->body());
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Validate payment request
     */
    private function validatePaymentRequest(array $data): void
    {
        if (!isset($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('Invalid payment amount');
        }

        if (!isset($data['student_id'])) {
            throw new \Exception('Student ID is required');
        }

        $student = Student::find($data['student_id']);
        if (!$student) {
            throw new \Exception('Student not found');
        }

        // Check for minimum and maximum amounts
        $minAmount = config('payment.min_amount', 1);
        $maxAmount = config('payment.max_amount', 100000);

        if ($data['amount'] < $minAmount || $data['amount'] > $maxAmount) {
            throw new \Exception("Payment amount must be between {$minAmount} and {$maxAmount}");
        }

        // Rate limiting check (simplified)
        $rateLimitKey = 'payment_attempt_' . ($data['student_id'] ?? 'anonymous');
        if (Cache::has($rateLimitKey)) {
            throw new \Exception('Please wait before making another payment attempt');
        }

        // Set rate limit (5 minutes per attempt)
        Cache::put($rateLimitKey, true, 300);
    }

    /**
     * Create payment record
     */
    private function createPaymentRecord(array $data): Payment
    {
        return Payment::create([
            'student_id' => $data['student_id'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? 'Online Payment',
            'payment_method' => $data['payment_method'],
            'reference_number' => $this->generateReferenceNumber(),
            'status' => Payment::STATUS_PENDING,
            'meta' => [
                'gateway' => $data['payment_method'],
                'initiated_at' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }

    /**
     * Store gateway-specific payment details
     */
    private function storeGatewayDetails(Payment $payment, string $gateway, array $result): void
    {
        $payment->gatewayDetails()->create([
            'gateway' => $gateway,
            'gateway_transaction_id' => $result['gateway_transaction_id'] ?? null,
            'gateway_response_data' => $result,
            'gateway_status' => 'initiated',
        ]);
    }

    /**
     * Generate unique reference number
     */
    private function generateReferenceNumber(): string
    {
        do {
            $reference = 'PAY' . date('Ymd') . strtoupper(Str::random(8));
        } while (Payment::where('reference_number', $reference)->exists());

        return $reference;
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods(): array
    {
        return [
            self::GATEWAY_GCASH => [
                'name' => 'GCash',
                'description' => 'Pay with GCash QR Code',
                'icon' => 'gcash-icon.png',
                'available' => $this->isGatewayConfigured(self::GATEWAY_GCASH),
                'fees' => config('payment.gcash.fees', []),
            ],
            self::GATEWAY_PAYPAL => [
                'name' => 'PayPal',
                'description' => 'Pay with PayPal, Credit Card, or Debit Card',
                'icon' => 'paypal-icon.png',
                'available' => $this->isGatewayConfigured(self::GATEWAY_PAYPAL),
                'fees' => config('payment.paypal.fees', []),
            ],
            self::GATEWAY_STRIPE => [
                'name' => 'Stripe',
                'description' => 'Pay with Credit Card, Debit Card, or Apple Pay',
                'icon' => 'stripe-icon.png',
                'available' => $this->isGatewayConfigured(self::GATEWAY_STRIPE),
                'fees' => config('payment.stripe.fees', []),
            ],
        ];
    }

    /**
     * Check if gateway is properly configured
     */
    private function isGatewayConfigured(string $gateway): bool
    {
        $credentials = $this->credentials[$gateway] ?? [];
        $endpoints = $this->endpoints[$gateway] ?? [];

        return match ($gateway) {
            self::GATEWAY_GCASH => isset($credentials['api_key'], $credentials['api_secret']) && !empty($endpoints),
            self::GATEWAY_PAYPAL => isset($credentials['client_id'], $credentials['client_secret']) && !empty($endpoints),
            self::GATEWAY_STRIPE => isset($credentials['secret_key']) && !empty($endpoints),
            default => false,
        };
    }

    /**
     * Calculate payment gateway fees
     */
    public function calculateGatewayFees(float $amount, string $gateway): float
    {
        $config = config("payment.{$gateway}.fees", []);

        if (empty($config)) {
            return 0;
        }

        $fees = 0;

        // Fixed fee
        if (isset($config['fixed'])) {
            $fees += $config['fixed'];
        }

        // Percentage fee
        if (isset($config['percentage'])) {
            $fees += ($amount * $config['percentage']) / 100;
        }

        return round($fees, 2);
    }

    /**
     * Process webhook callback from gateway
     */
    public function processWebhook(string $gateway, array $payload): array
    {
        return match ($gateway) {
            self::GATEWAY_GCASH => $this->processGCashWebhook($payload),
            self::GATEWAY_PAYPAL => $this->processPayPalWebhook($payload),
            self::GATEWAY_STRIPE => $this->processStripeWebhook($payload),
            default => throw new \Exception("Unsupported gateway webhook: {$gateway}")
        };
    }

    /**
     * Process GCash webhook
     */
    private function processGCashWebhook(array $payload): array
    {
        // Validate webhook signature
        if (!$this->validateGCashWebhook($payload)) {
            throw new \Exception('Invalid GCash webhook signature');
        }

        $transactionId = $payload['qr_id'] ?? null;
        $status = $payload['status'] ?? null;
        $amount = $payload['amount'] ?? 0;

        // Find payment by gateway transaction ID
        $paymentDetail = \App\Models\PaymentGatewayDetail::where('gateway', self::GATEWAY_GCASH)
            ->where('gateway_transaction_id', $transactionId)
            ->first();

        if (!$paymentDetail) {
            throw new \Exception('Payment not found for GCash transaction');
        }

        $payment = $paymentDetail->payment;

        // Update payment status based on webhook status
        $newStatus = match ($status) {
            'SUCCESS' => Payment::STATUS_COMPLETED,
            'FAILED', 'CANCELLED' => Payment::STATUS_FAILED,
            'EXPIRED' => Payment::STATUS_CANCELLED,
            default => $payment->status
        };

        if ($newStatus !== $payment->status) {
            $payment->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === Payment::STATUS_COMPLETED ? now() : $payment->paid_at,
            ]);

            // Update gateway details
            $paymentDetail->update([
                'gateway_status' => $status,
                'gateway_response_data' => array_merge(
                    $paymentDetail->gateway_response_data ?? [],
                    $payload,
                    ['processed_at' => now()->toISOString()]
                ),
            ]);
        }

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'status' => $newStatus,
            'message' => 'Payment status updated successfully'
        ];
    }

    /**
     * Process PayPal webhook
     */
    private function processPayPalWebhook(array $payload): array
    {
        // PayPal webhook validation would go here
        // For now, handle basic payment completion

        $eventType = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $paypalTransactionId = $resource['id'] ?? null;

            $paymentDetail = \App\Models\PaymentGatewayDetail::where('gateway', self::GATEWAY_PAYPAL)
                ->where('gateway_transaction_id', $paypalTransactionId)
                ->first();

            if ($paymentDetail) {
                $payment = $paymentDetail->payment;
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'status' => Payment::STATUS_COMPLETED,
                    'message' => 'PayPal payment completed'
                ];
            }
        }

        return ['success' => false, 'message' => 'Unhandled webhook event'];
    }

    /**
     * Process Stripe webhook
     */
    private function processStripeWebhook(array $payload): array
    {
        $eventType = $payload['type'] ?? '';

        if ($eventType === 'checkout.session.completed') {
            $session = $payload['data']['object'] ?? [];
            $stripeSessionId = $session['id'] ?? null;

            $paymentDetail = \App\Models\PaymentGatewayDetail::where('gateway', self::GATEWAY_STRIPE)
                ->where('gateway_transaction_id', $stripeSessionId)
                ->first();

            if ($paymentDetail) {
                $payment = $paymentDetail->payment;
                $payment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'status' => Payment::STATUS_COMPLETED,
                    'message' => 'Stripe payment completed'
                ];
            }
        }

        return ['success' => false, 'message' => 'Unhandled Stripe webhook event'];
    }

    /**
     * Validate GCash webhook signature
     */
    private function validateGCashWebhook(array $payload): bool
    {
        // Implement webhook signature validation
        // This would use the GCash webhook secret
        $signature = request()->header('X-GCash-Signature');
        $webhookSecret = $this->credentials['gcash']['webhook_secret'] ?? '';

        if (!$signature || !$webhookSecret) {
            return false;
        }

        // Simplified validation - implement proper HMAC verification
        return hash_hmac('sha256', json_encode($payload), $webhookSecret) === $signature;
    }
}