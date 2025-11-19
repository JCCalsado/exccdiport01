<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PaymentSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Log payment request
        $this->logPaymentRequest($request);

        // Validate request origin
        if (!$this->validateRequestOrigin($request)) {
            Log::warning('Suspicious payment request origin', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Request validation failed'
            ], 403);
        }

        // Validate CSRF token for web requests
        if ($request->isMethod('POST') && !$request->ajax() && !$request->header('X-Requested-With')) {
            if (!$this->validateCsrfToken($request)) {
                Log::warning('Invalid CSRF token in payment request', [
                    'ip' => $request->ip(),
                    'endpoint' => $request->path(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Security validation failed'
                ], 403);
            }
        }

        // Rate limiting
        if (!$this->checkRateLimit($request)) {
            Log::warning('Payment rate limit exceeded', [
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many payment attempts. Please wait before trying again.'
            ], 429);
        }

        // IP validation
        if (!$this->validateIpAddress($request)) {
            Log::warning('Blocked IP attempting payment', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Access denied from your location'
            ], 403);
        }

        // User session validation
        if (!$this->validateUserSession($request)) {
            Log::warning('Invalid user session for payment', [
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Session validation failed. Please login again.'
            ], 401);
        }

        // Request size validation
        if (!$this->validateRequestSize($request)) {
            Log::warning('Oversized payment request', [
                'ip' => $request->ip(),
                'content_length' => $request->header('Content-Length'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Request too large'
            ], 413);
        }

        // Request timing validation (prevent automated attacks)
        if (!$this->validateRequestTiming($request)) {
            Log::warning('Suspicious payment request timing', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Request validation failed'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Log payment request for audit trail
     */
    private function logPaymentRequest(Request $request): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'content_length' => $request->header('Content-Length'),
            'referer' => $request->header('referer'),
        ];

        // Add geolocation if available
        $ipLocation = $this->getIpLocation($request->ip());
        if ($ipLocation) {
            $logData['location'] = $ipLocation;
        }

        Log::channel('payments')->info('Payment request', $logData);
    }

    /**
     * Validate request origin
     */
    private function validateRequestOrigin(Request $request): bool
    {
        $allowedOrigins = config('payment.security.allowed_origins', []);
        $referer = $request->header('referer');
        $origin = $request->header('origin');

        // Allow same-origin requests
        if (!$referer && !$origin) {
            return true;
        }

        // Check if origin is allowed
        if ($origin) {
            foreach ($allowedOrigins as $allowedOrigin) {
                if (Str::startsWith($origin, $allowedOrigin)) {
                    return true;
                }
            }
        }

        // Check if referer is allowed
        if ($referer) {
            foreach ($allowedOrigins as $allowedOrigin) {
                if (Str::startsWith($referer, $allowedOrigin)) {
                    return true;
                }
            }
        }

        // Allow direct requests (webhook endpoints)
        $allowedDirectPaths = [
            'payments/webhook/gcash',
            'payments/webhook/paypal',
            'payments/webhook/stripe',
        ];

        foreach ($allowedDirectPaths as $path) {
            if (Str::contains($request->path(), $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken(Request $request): bool
    {
        // Skip CSRF validation for webhook endpoints
        $skipCsrfPaths = [
            'payments/webhook',
        ];

        foreach ($skipCsrfPaths as $path) {
            if (Str::contains($request->path(), $path)) {
                return true;
            }
        }

        // Laravel's built-in CSRF validation
        return $request->hasValidSignature() ||
               $request->session()->token() === $request->input('_token') ||
               $request->header('X-CSRF-TOKEN') === $request->session()->token();
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(Request $request): bool
    {
        $key = $this->getRateLimitKey($request);
        $maxAttempts = config('payment.security.rate_limit_attempts', 5);
        $decayMinutes = config('payment.security.rate_limit_minutes', 1);

        return !RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Get rate limit key
     */
    private function getRateLimitKey(Request $request): string
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();

        // Use user ID if authenticated, otherwise IP
        $identifier = $userId ? 'user:' . $userId : 'ip:' . $ip;

        return 'payment:' . $identifier;
    }

    /**
     * Validate IP address against whitelist/blacklist
     */
    private function validateIpAddress(Request $request): bool
    {
        $ip = $request->ip();
        $whitelist = config('payment.security.ip_whitelist', []);
        $blacklist = config('payment.security.ip_blacklist', []);

        // Check blacklist first
        foreach ($blacklist as $blockedIp) {
            if ($this->ipMatches($ip, $blockedIp)) {
                return false;
            }
        }

        // If whitelist is not empty, check whitelist
        if (!empty($whitelist)) {
            foreach ($whitelist as $allowedIp) {
                if ($this->ipMatches($ip, $allowedIp)) {
                    return true;
                }
            }
            return false; // Not in whitelist
        }

        return true; // Not blacklisted and whitelist is empty
    }

    /**
     * Check if IP matches pattern (supports CIDR notation)
     */
    private function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation support (simplified)
        if (str_contains($pattern, '/')) {
            [$network, $prefix] = explode('/', $pattern);
            return $this->ipInCidr($ip, $network, (int)$prefix);
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInCidr(string $ip, string $network, int $prefix): bool
    {
        // Simplified CIDR check - in production, use proper CIDR library
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $mask = -1 << (32 - $prefix);

        return ($ipLong & $mask) === ($networkLong & $mask);
    }

    /**
     * Validate user session
     */
    private function validateUserSession(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        // Check if user session is active
        if (!$request->session()->has('auth.password_confirmed_at')) {
            // For sensitive operations, require password reconfirmation
            $lastActivity = $request->session()->get('last_activity', 0);
            $timeout = config('session.lifetime', 120) * 60; // Convert to seconds

            if (time() - $lastActivity > $timeout) {
                return false;
            }
        }

        // Update last activity
        $request->session()->put('last_activity', time());

        return true;
    }

    /**
     * Validate request size
     */
    private function validateRequestSize(Request $request): bool
    {
        $maxSize = config('payment.security.max_request_size', 1024 * 1024); // 1MB default
        $contentLength = (int) $request->header('Content-Length', 0);

        return $contentLength <= $maxSize;
    }

    /**
     * Validate request timing to prevent automated attacks
     */
    private function validateRequestTiming(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return true; // Skip for unauthenticated requests
        }

        $cacheKey = 'payment_timing:' . $user->id;
        $lastRequest = Cache::get($cacheKey);
        $minInterval = config('payment.security.min_request_interval', 2); // 2 seconds

        if ($lastRequest && (time() - $lastRequest) < $minInterval) {
            return false;
        }

        Cache::put($cacheKey, time(), 60); // Remember for 1 minute

        return true;
    }

    /**
     * Get IP location information
     */
    private function getIpLocation(string $ip): ?array
    {
        // In production, integrate with a proper IP geolocation service
        // For now, return null to avoid external dependencies

        // Example integration would be:
        // $response = Http::get("http://ip-api.com/json/{$ip}");
        // return $response->successful() ? $response->json() : null;

        return null;
    }
}