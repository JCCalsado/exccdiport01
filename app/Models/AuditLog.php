<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'user_id',
        'ip_address',
        'user_agent',
        'data',
        'description',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get logs by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: Get logs by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get logs by date range
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
    }

    /**
     * Scope: Get security-related logs
     */
    public function scopeSecurity($query)
    {
        return $query->where('action', 'like', 'security_%');
    }

    /**
     * Scope: Get financial logs
     */
    public function scopeFinancial($query)
    {
        return $query->where('action', 'like', 'financial_%');
    }

    /**
     * Scope: Get bulk operation logs
     */
    public function scopeBulkOperations($query)
    {
        return $query->where('action', 'like', 'bulk_%');
    }

    /**
     * Get action type label
     */
    public function getActionTypeLabelAttribute(): string
    {
        if (str_starts_with($this->action, 'financial_')) {
            return 'Financial';
        }

        if (str_starts_with($this->action, 'security_')) {
            return 'Security';
        }

        if (str_starts_with($this->action, 'bulk_')) {
            return 'Bulk Operation';
        }

        if (str_starts_with($this->action, 'config_')) {
            return 'Configuration';
        }

        if (str_starts_with($this->action, 'data_access_')) {
            return 'Data Access';
        }

        return 'General';
    }

    /**
     * Get severity level based on action
     */
    public function getSeverityLevelAttribute(): string
    {
        $highSeverityActions = [
            'security_unauthorized_access',
            'security_suspicious_activity',
            'financial_refund',
            'bulk_fee_waiver',
        ];

        $mediumSeverityActions = [
            'financial_payment',
            'security_login_attempt',
            'config_change',
            'bulk_fee_update',
        ];

        if (in_array($this->action, $highSeverityActions)) {
            return 'high';
        }

        if (in_array($this->action, $mediumSeverityActions)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get severity badge class for UI
     */
    public function getSeverityBadgeClassAttribute(): string
    {
        return match ($this->severity_level) {
            'high' => 'bg-red-100 text-red-800',
            'medium' => 'bg-yellow-100 text-yellow-800',
            'low' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get formatted data for display
     */
    public function getFormattedDataAttribute(): array
    {
        $data = $this->data ?? [];

        // Format common data fields
        if (isset($data['amount'])) {
            $data['formatted_amount'] = '₱' . number_format($data['amount'], 2);
        }

        if (isset($data['percentage'])) {
            $data['formatted_percentage'] = number_format($data['percentage'], 1) . '%';
        }

        return $data;
    }

    /**
     * Check if log entry is critical
     */
    public function isCritical(): bool
    {
        return $this->severity_level === 'high';
    }

    /**
     * Check if log entry is a financial transaction
     */
    public function isFinancial(): bool
    {
        return str_starts_with($this->action, 'financial_');
    }

    /**
     * Check if log entry is a security event
     */
    public function isSecurityEvent(): bool
    {
        return str_starts_with($this->action, 'security_');
    }

    /**
     * Get human-readable action description
     */
    public function getHumanReadableActionAttribute(): string
    {
        $actions = [
            'financial_payment' => 'Payment Processed',
            'financial_refund' => 'Refund Issued',
            'financial_adjustment' => 'Fee Adjustment',
            'financial_waiver' => 'Fee Waiver',
            'security_login_attempt' => 'Login Attempt',
            'security_unauthorized_access' => 'Unauthorized Access',
            'security_suspicious_activity' => 'Suspicious Activity',
            'security_password_change' => 'Password Changed',
            'security_data_export' => 'Data Export',
            'bulk_fee_assignment' => 'Bulk Fee Assignment',
            'bulk_fee_update' => 'Bulk Fee Update',
            'bulk_fee_waiver' => 'Bulk Fee Waiver',
            'bulk_payment_reminders' => 'Bulk Payment Reminders',
            'bulk_student_export' => 'Bulk Student Export',
            'config_change' => 'Configuration Changed',
            'data_access_student' => 'Student Data Accessed',
            'data_access_payment' => 'Payment Data Accessed',
            'data_access_report' => 'Report Data Accessed',
        ];

        return $actions[$this->action] ?? ucwords(str_replace('_', ' ', $this->action));
    }
}