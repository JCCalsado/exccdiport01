<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\AuditLog;

class AuditService
{
    /**
     * Log an audit trail entry
     */
    public function log(string $action, int $userId, array $data = [], ?string $description = null): void
    {
        try {
            $auditData = [
                'action' => $action,
                'user_id' => $userId,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'data' => $data,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Add additional context if available
            if (request()?->hasHeader('X-Forwarded-For')) {
                $auditData['ip_address'] = request()->header('X-Forwarded-For');
            }

            AuditLog::create($auditData);

            // Also log to system logs for critical actions
            $this->logToSystemLog($action, $userId, $data, $description);

        } catch (\Exception $e) {
            Log::error('Failed to create audit log entry', [
                'error' => $e->getMessage(),
                'action' => $action,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Log a bulk operation
     */
    public function logBulkOperation(string $operation, int $userId, array $details = []): void
    {
        $this->log($operation, $userId, $details, $this->generateBulkOperationDescription($operation, $details));
    }

    /**
     * Log a financial transaction
     */
    public function logFinancialTransaction(string $transactionType, int $userId, array $transactionData): void
    {
        $this->log(
            "financial_{$transactionType}",
            $userId,
            $transactionData,
            $this->generateFinancialTransactionDescription($transactionType, $transactionData)
        );
    }

    /**
     * Log a data access event
     */
    public function logDataAccess(string $resource, int $userId, array $accessDetails = []): void
    {
        $this->log(
            "data_access_{$resource}",
            $userId,
            $accessDetails,
            "Accessed {$resource} data"
        );
    }

    /**
     * Log a system configuration change
     */
    public function logConfigurationChange(string $configKey, int $userId, $oldValue, $newValue): void
    {
        $this->log(
            "config_change_{$configKey}",
            $userId,
            [
                'config_key' => $configKey,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ],
            "Changed configuration: {$configKey}"
        );
    }

    /**
     * Log a security event
     */
    public function logSecurityEvent(string $eventType, int $userId = null, array $eventData = []): void
    {
        $this->log(
            "security_{$eventType}",
            $userId ?? 0,
            $eventData,
            $this->generateSecurityEventDescription($eventType, $eventData)
        );
    }

    /**
     * Log a failed operation
     */
    public function logFailure(string $operation, int $userId, array $failureData, string $errorMessage): void
    {
        $this->log(
            "failure_{$operation}",
            $userId,
            array_merge($failureData, ['error_message' => $errorMessage]),
            "Failed operation: {$operation} - {$errorMessage}"
        );
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserAuditLogs(int $userId, int $limit = 100, array $filters = [])
    {
        $query = AuditLog::where('user_id', $userId);

        // Apply filters
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs by action type
     */
    public function getLogsByAction(string $action, int $limit = 100)
    {
        return AuditLog::where('action', $action)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get security-related audit logs
     */
    public function getSecurityLogs(int $days = 30)
    {
        return AuditLog::where('action', 'like', 'security_%')
            ->where('created_at', '>=', now()->subDays($days))
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get financial audit logs
     */
    public function getFinancialLogs(int $days = 30, array $filters = [])
    {
        $query = AuditLog::where('action', 'like', 'financial_%')
            ->where('created_at', '>=', now()->subDays($days));

        if (!empty($filters['transaction_type'])) {
            $query->where('action', "financial_{$filters['transaction_type']}");
        }

        return $query->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Generate bulk operation description
     */
    private function generateBulkOperationDescription(string $operation, array $details): string
    {
        switch ($operation) {
            case 'bulk_fee_assignment':
                return "Bulk assigned fees to {$details['student_count']} students ({$details['fee_count']} fees)";

            case 'bulk_fee_update':
                return "Bulk updated {$details['updated_count']} fee items ({$details['update_type']})";

            case 'bulk_fee_waiver':
                $amount = number_format($details['total_waived_amount'] ?? 0, 2);
                return "Bulk waived fees for {$details['waived_count']} items (total: ₱{$amount})";

            case 'bulk_payment_reminders':
                return "Bulk sent payment reminders to {$details['sent_count']} students";

            case 'bulk_student_export':
                return "Bulk exported {$details['student_count']} students in {$details['export_format']} format";

            default:
                return "Bulk operation: {$operation}";
        }
    }

    /**
     * Generate financial transaction description
     */
    private function generateFinancialTransactionDescription(string $transactionType, array $data): string
    {
        $amount = number_format($data['amount'] ?? 0, 2);

        switch ($transactionType) {
            case 'payment':
                $method = $data['payment_method'] ?? 'unknown';
                return "Payment processed: ₱{$amount} via {$method}";

            case 'refund':
                return "Refund issued: ₱{$amount}";

            case 'adjustment':
                $type = $data['adjustment_type'] ?? 'unknown';
                return "Fee adjustment ({$type}): ₱{$amount}";

            case 'waiver':
                return "Fee waiver: ₱{$amount}";

            default:
                return "Financial transaction ({$transactionType}): ₱{$amount}";
        }
    }

    /**
     * Generate security event description
     */
    private function generateSecurityEventDescription(string $eventType, array $eventData): string
    {
        switch ($eventType) {
            case 'login_attempt':
                $status = $eventData['success'] ? 'successful' : 'failed';
                $ip = $eventData['ip_address'] ?? 'unknown';
                return "Login {$status} from IP: {$ip}";

            case 'password_change':
                return "Password changed";

            case 'unauthorized_access':
                $resource = $eventData['resource'] ?? 'unknown';
                return "Unauthorized access attempt to: {$resource}";

            case 'suspicious_activity':
                $reason = $eventData['reason'] ?? 'unknown';
                return "Suspicious activity detected: {$reason}";

            case 'data_export':
                $resource = $eventData['resource'] ?? 'unknown';
                $format = $eventData['format'] ?? 'unknown';
                return "Data export: {$resource} ({$format})";

            default:
                return "Security event: {$eventType}";
        }
    }

    /**
     * Log to system logs for critical actions
     */
    private function logToSystemLog(string $action, int $userId, array $data, ?string $description): void
    {
        $criticalActions = [
            'financial_payment',
            'financial_refund',
            'security_login_attempt',
            'security_unauthorized_access',
            'config_change',
            'bulk_fee_waiver',
        ];

        if (in_array($action, $criticalActions)) {
            Log::info('AUDIT: ' . ($description ?? $action), [
                'action' => $action,
                'user_id' => $userId,
                'data' => $data,
            ]);
        }
    }

    /**
     * Cleanup old audit logs (call from scheduled job)
     */
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        $deletedCount = AuditLog::where('created_at', '<', $cutoffDate)->delete();

        Log::info("Audit cleanup completed", [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate,
        ]);

        return $deletedCount;
    }

    /**
     * Export audit logs for compliance
     */
    public function exportAuditLogs(array $filters = []): string
    {
        $query = AuditLog::with('user');

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV content
        $csvContent = "ID,Action,User,User ID,IP Address,Description,Date Created\n";

        foreach ($logs as $log) {
            $csvContent .= sprintf(
                "%d,%s,%s,%d,%s,\"%s\",%s\n",
                $log->id,
                $log->action,
                $log->user?->name ?? 'System',
                $log->user_id,
                $log->ip_address ?? 'N/A',
                str_replace('"', '""', $log->description ?? $log->action),
                $log->created_at->format('Y-m-d H:i:s')
            );
        }

        // Generate temporary file
        $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = storage_path('app/temp/' . $filename);

        file_put_contents($filepath, $csvContent);

        return $filename;
    }
}