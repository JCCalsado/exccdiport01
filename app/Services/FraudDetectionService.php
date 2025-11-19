<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Payment;
use App\Models\Student;

class FraudDetectionService
{
    /**
     * Check if payment request is suspicious
     */
    public function isSuspicious(array $paymentData, Student $student): bool
    {
        $fraudScore = 0;
        $checks = [];

        // Check 1: Unusual payment amount vs. typical student fees
        $amountCheck = $this->checkUnusualAmount($paymentData['amount'], $student);
        $fraudScore += $amountCheck['score'];
        $checks['amount'] = $amountCheck;

        // Check 2: Multiple failed payment attempts
        $failedAttemptsCheck = $this->checkFailedAttempts($student);
        $fraudScore += $failedAttemptsCheck['score'];
        $checks['failed_attempts'] = $failedAttemptsCheck;

        // Check 3: Suspicious payment patterns
        $patternCheck = $this->checkSuspiciousPatterns($paymentData, $student);
        $fraudScore += $patternCheck['score'];
        $checks['patterns'] = $patternCheck;

        // Check 4: Geographic location validation
        $locationCheck = $this->checkGeographicLocation($student);
        $fraudScore += $locationCheck['score'];
        $checks['location'] = $locationCheck;

        // Check 5: Velocity checks (frequency of payments)
        $velocityCheck = $this->checkPaymentVelocity($student);
        $fraudScore += $velocityCheck['score'];
        $checks['velocity'] = $velocityCheck;

        // Check 6: Device fingerprinting
        $deviceCheck = $this->checkDeviceFingerprint($paymentData, $student);
        $fraudScore += $deviceCheck['score'];
        $checks['device'] = $deviceCheck;

        // Check 7: Payment method anomalies
        $paymentMethodCheck = $this->checkPaymentMethodAnomalies($paymentData, $student);
        $fraudScore += $paymentMethodCheck['score'];
        $checks['payment_method'] = $paymentMethodCheck;

        // Log the analysis
        Log::info('Fraud detection analysis', [
            'student_id' => $student->id,
            'amount' => $paymentData['amount'] ?? 0,
            'payment_method' => $paymentData['payment_method'] ?? 'unknown',
            'fraud_score' => $fraudScore,
            'threshold' => config('payment.fraud.threshold', 50),
            'checks' => $checks,
        ]);

        // Block if fraud score exceeds threshold
        $threshold = config('payment.fraud.threshold', 50);
        $isSuspicious = $fraudScore >= $threshold;

        // Store analysis for review
        $this->storeFraudAnalysis($student, $paymentData, $fraudScore, $checks, $isSuspicious);

        return $isSuspicious;
    }

    /**
     * Check for unusual payment amounts
     */
    private function checkUnusualAmount(float $amount, Student $student): array
    {
        // Get student's typical payment amounts
        $typicalPayments = Payment::where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->pluck('amount');

        if ($typicalPayments->isEmpty()) {
            return [
                'score' => 0,
                'reason' => 'No payment history for comparison',
                'risk_level' => 'low'
            ];
        }

        $averageAmount = $typicalPayments->avg();
        $maxAmount = $typicalPayments->max();
        $minAmount = $typicalPayments->min();

        // Check if amount is unusually high (> 3x average or > 5x max)
        if ($amount > ($averageAmount * 3) || $amount > ($maxAmount * 5)) {
            return [
                'score' => 25,
                'reason' => "Amount ({$amount}) is unusually high compared to average ({$averageAmount})",
                'risk_level' => 'high'
            ];
        }

        // Check if amount is unusually small (< 10% of average)
        if ($amount < ($averageAmount * 0.1) && $amount > 0) {
            return [
                'score' => 10,
                'reason' => "Amount ({$amount}) is unusually small compared to typical payments",
                'risk_level' => 'medium'
            ];
        }

        return [
            'score' => 0,
            'reason' => 'Payment amount within normal range',
            'risk_level' => 'low'
        ];
    }

    /**
     * Check for multiple failed payment attempts
     */
    private function checkFailedAttempts(Student $student): array
    {
        $timeWindow = config('payment.fraud.failed_attempts_window', 60); // 60 minutes
        $maxFailed = config('payment.fraud.max_failed_attempts', 5);

        $failedCount = Payment::where('student_id', $student->id)
            ->where('status', Payment::STATUS_FAILED)
            ->where('created_at', '>', now()->subMinutes($timeWindow))
            ->count();

        if ($failedCount >= $maxFailed) {
            return [
                'score' => 30,
                'reason' => "{$failedCount} failed payment attempts in the last {$timeWindow} minutes",
                'risk_level' => 'high'
            ];
        }

        if ($failedCount >= $maxFailed / 2) {
            return [
                'score' => 15,
                'reason' => "{$failedCount} failed payment attempts in the last {$timeWindow} minutes",
                'risk_level' => 'medium'
            ];
        }

        return [
            'score' => 0,
            'reason' => 'No excessive failed attempts detected',
            'risk_level' => 'low'
        ];
    }

    /**
     * Check for suspicious payment patterns
     */
    private function checkSuspiciousPatterns(array $paymentData, Student $student): array
    {
        $score = 0;
        $reasons = [];

        // Pattern 1: Round numbers (potential testing)
        if ($paymentData['amount'] % 1000 == 0 && $paymentData['amount'] > 1000) {
            $score += 10;
            $reasons[] = 'Round amount payment (possible testing)';
        }

        // Pattern 2: Multiple payment methods in short time
        $recentMethods = Payment::where('student_id', $student->id)
            ->where('created_at', '>', now()->subHours(24))
            ->distinct('payment_method')
            ->count();

        if ($recentMethods >= 3) {
            $score += 15;
            $reasons[] = "Used {$recentMethods} different payment methods in 24 hours";
        }

        // Pattern 3: Rapid payments
        $recentPayments = Payment::where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('created_at', '>', now()->subMinutes(30))
            ->count();

        if ($recentPayments >= 3) {
            $score += 20;
            $reasons[] = "{$recentPayments} payments in last 30 minutes";
        }

        // Pattern 4: Always minimum amount
        $minPayments = Payment::where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('amount', '<=', config('payment.min_amount', 1) * 1.1)
            ->count();

        $totalPayments = Payment::where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->count();

        if ($totalPayments >= 5 && ($minPayments / $totalPayments) > 0.8) {
            $score += 10;
            $reasons[] = 'Consistently paying minimum amounts';
        }

        return [
            'score' => $score,
            'reason' => implode('; ', $reasons) ?: 'No suspicious patterns detected',
            'risk_level' => $score > 15 ? 'high' : ($score > 5 ? 'medium' : 'low')
        ];
    }

    /**
     * Check geographic location consistency
     */
    private function checkGeographicLocation(Student $student): array
    {
        $currentIp = request()->ip();
        $cacheKey = "student_location_{$student->id}";

        // Get stored location history
        $locationHistory = Cache::get($cacheKey, []);
        $currentLocation = $this->getLocationData($currentIp);

        // Skip if we can't determine location
        if (!$currentLocation) {
            return [
                'score' => 0,
                'reason' => 'Unable to determine location',
                'risk_level' => 'low'
            ];
        }

        // Check for impossible travel
        foreach ($locationHistory as $location) {
            $distance = $this->calculateDistance(
                $currentLocation['lat'], $currentLocation['lon'],
                $location['lat'], $location['lon']
            );

            $timeDiff = now()->diffInMinutes($location['timestamp']);
            $maxSpeed = config('payment.fraud.max_travel_speed', 1000); // km/h

            if ($distance > ($timeDiff * $maxSpeed)) {
                // Update location history
                array_unshift($locationHistory, array_merge($currentLocation, ['timestamp' => now()]));
                $locationHistory = array_slice($locationHistory, 0, 10); // Keep last 10
                Cache::put($cacheKey, $locationHistory, 24 * 60 * 60); // 24 hours

                return [
                    'score' => 25,
                    'reason' => "Impossible travel detected: {$distance}km in {$timeDiff} minutes",
                    'risk_level' => 'high'
                ];
            }
        }

        // Update location history
        array_unshift($locationHistory, array_merge($currentLocation, ['timestamp' => now()]));
        $locationHistory = array_slice($locationHistory, 0, 10);
        Cache::put($cacheKey, $locationHistory, 24 * 60 * 60);

        // Check for multiple countries in short time
        $recentCountries = collect($locationHistory)
            ->take(5) // Last 5 locations
            ->pluck('country')
            ->unique()
            ->count();

        if ($recentCountries > 2) {
            return [
                'score' => 15,
                'reason' => "Access from {$recentCountries} different countries recently",
                'risk_level' => 'medium'
            ];
        }

        return [
            'score' => 0,
            'reason' => 'Location check passed',
            'risk_level' => 'low'
        ];
    }

    /**
     * Check payment velocity (frequency analysis)
     */
    private function checkPaymentVelocity(Student $student): array
    {
        $timeWindows = [
            'hour' => 60,
            'day' => 24 * 60,
            'week' => 7 * 24 * 60,
        ];

        $limits = [
            'hour' => config('payment.fraud.max_payments_hour', 5),
            'day' => config('payment.fraud.max_payments_day', 15),
            'week' => config('payment.fraud.max_payments_week', 50),
        ];

        $score = 0;
        $reasons = [];

        foreach ($timeWindows as $period => $minutes) {
            $count = Payment::where('student_id', $student->id)
                ->where('created_at', '>', now()->subMinutes($minutes))
                ->count();

            if ($count > $limits[$period]) {
                $score += 10;
                $reasons[] = "{$count} payments in last {$period} (limit: {$limits[$period]})";
            }
        }

        return [
            'score' => $score,
            'reason' => implode('; ', $reasons) ?: 'Payment velocity within limits',
            'risk_level' => $score > 10 ? 'high' : ($score > 0 ? 'medium' : 'low')
        ];
    }

    /**
     * Check device fingerprinting
     */
    private function checkDeviceFingerprint(array $paymentData, Student $student): array
    {
        $userAgent = request()->userAgent();
        $cacheKey = "student_devices_{$student->id}";

        $recentDevices = Cache::get($cacheKey, []);

        // Check if this device has been used before
        $deviceHash = md5($userAgent);
        $currentDevice = [
            'hash' => $deviceHash,
            'user_agent' => $userAgent,
            'ip' => request()->ip(),
            'first_seen' => now(),
            'last_used' => now(),
        ];

        $deviceFound = false;
        foreach ($recentDevices as &$device) {
            if ($device['hash'] === $deviceHash) {
                $deviceFound = true;
                $device['last_used'] = now();
                $device['ip'] = request()->ip();

                // Check if IP changed for same device
                if ($device['ip'] !== request()->ip()) {
                    return [
                        'score' => 15,
                        'reason' => 'Same device used from different IP address',
                        'risk_level' => 'medium'
                    ];
                }
                break;
            }
        }

        if (!$deviceFound) {
            // New device detected
            if (count($recentDevices) >= 5) {
                // Too many devices
                return [
                    'score' => 20,
                    'reason' => 'Payment attempted from new device (too many devices)',
                    'risk_level' => 'high'
                ];
            }

            $recentDevices[] = $currentDevice;

            // New device penalty
            return [
                'score' => 10,
                'reason' => 'First payment from this device',
                'risk_level' => 'medium'
            ];
        }

        // Update device cache
        Cache::put($cacheKey, $recentDevices, 30 * 24 * 60 * 60); // 30 days

        return [
            'score' => 0,
            'reason' => 'Device fingerprint check passed',
            'risk_level' => 'low'
        ];
    }

    /**
     * Check for payment method anomalies
     */
    private function checkPaymentMethodAnomalies(array $paymentData, Student $student): array
    {
        $paymentMethod = $paymentData['payment_method'];

        // Get student's payment method history
        $methodHistory = Payment::where('student_id', $student->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->groupBy('payment_method')
            ->selectRaw('payment_method, count(*) as count')
            ->pluck('count', 'payment_method');

        if ($methodHistory->isEmpty()) {
            // First payment - no history to compare
            return [
                'score' => 0,
                'reason' => 'First payment from student',
                'risk_level' => 'low'
            ];
        }

        $totalPayments = $methodHistory->sum();
        $methodUsage = $methodHistory->get($paymentMethod, 0);
        $methodPercentage = ($methodUsage / $totalPayments) * 100;

        // If using a method they've never used before
        if ($methodUsage === 0) {
            return [
                'score' => 5,
                'reason' => "Using {$paymentMethod} for the first time",
                'risk_level' => 'low'
            ];
        }

        // If using a very rarely used method (< 10% of payments)
        if ($methodPercentage < 10 && $totalPayments >= 5) {
            return [
                'score' => 10,
                'reason' => "Using {$paymentMethod} which represents only {$methodPercentage}% of payment history",
                'risk_level' => 'medium'
            ];
        }

        return [
            'score' => 0,
            'reason' => 'Payment method usage within normal pattern',
            'risk_level' => 'low'
        ];
    }

    /**
     * Store fraud analysis for review
     */
    private function storeFraudAnalysis(Student $student, array $paymentData, int $fraudScore, array $checks, bool $isSuspicious): void
    {
        $analysis = [
            'student_id' => $student->id,
            'payment_data' => $paymentData,
            'fraud_score' => $fraudScore,
            'is_suspicious' => $isSuspicious,
            'checks' => $checks,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ];

        // In a real implementation, store this in a dedicated fraud_analyses table
        // For now, log it
        Log::channel('fraud')->info('Fraud analysis stored', $analysis);

        // If suspicious, alert administrators
        if ($isSuspicious) {
            $this->alertSuspiciousActivity($student, $analysis);
        }
    }

    /**
     * Alert administrators about suspicious activity
     */
    private function alertSuspiciousActivity(Student $student, array $analysis): void
    {
        $adminEmails = config('payment.fraud.alert_emails', []);

        foreach ($adminEmails as $email) {
            // Send email alert (implement email notification)
            Log::info("Fraud alert sent to {$email}", [
                'student_id' => $student->id,
                'fraud_score' => $analysis['fraud_score'],
            ]);
        }

        // Store in suspicious activities cache for admin review
        $cacheKey = 'suspicious_activities_' . date('Y-m-d');
        $activities = Cache::get($cacheKey, []);
        $activities[] = $analysis;
        Cache::put($cacheKey, $activities, 7 * 24 * 60 * 60); // 7 days
    }

    /**
     * Get location data for IP address
     */
    private function getLocationData(string $ip): ?array
    {
        // In production, integrate with IP geolocation service
        // For now, return mock data or null

        // Example implementation:
        // $response = Http::get("http://ip-api.com/json/{$ip}");
        // if ($response->successful()) {
        //     $data = $response->json();
        //     return [
        //         'lat' => $data['lat'],
        //         'lon' => $data['lon'],
        //         'country' => $data['country'],
        //         'city' => $data['city'],
        //     ];
        // }

        return null;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}