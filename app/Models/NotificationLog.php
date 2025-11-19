<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'recipient',
        'content',
        'subject',
        'status',
        'error_message',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'content' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Get the user that owns the notification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notification is read
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is delivered
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered' || $this->isRead();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        return $this->update([
            'read_at' => now(),
            'status' => 'delivered',
        ]);
    }

    /**
     * Get notification type label
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'payment_completed' => 'Payment Completed',
            'payment_failed' => 'Payment Failed',
            'payment_initiated' => 'Payment Initiated',
            'assessment_created' => 'Assessment Created',
            'account_balance_low' => 'Low Balance Alert',
            'admin_payment_completed' => 'Student Payment',
            'admin_payment_failed' => 'Payment Failure',
        ];

        return $labels[$this->notification_type] ?? ucwords(str_replace('_', ' ', $this->notification_type));
    }

    /**
     * Get channel label
     */
    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'Email',
            'sms' => 'SMS',
            'in_app' => 'In-App',
            'push' => 'Push Notification',
            default => ucfirst($this->channel),
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'bounced' => 'Bounced',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'sent' => 'bg-blue-100 text-blue-800',
            'delivered' => 'bg-green-100 text-green-800',
            'failed', 'bounced' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get channel icon for UI
     */
    public function getChannelIconAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'heroicon-o-envelope',
            'sms' => 'heroicon-o-chat-bubble-left-right',
            'in_app' => 'heroicon-o-bell',
            'push' => 'heroicon-o-device-phone-mobile',
            default => 'heroicon-o-bell',
        };
    }

    /**
     * Scope: Unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: Read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope: By channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope: By notification type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Recent notifications
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted content for display
     */
    public function getFormattedContentAttribute(): array
    {
        $content = $this->content ?? [];

        return [
            'title' => $content['title'] ?? 'Notification',
            'message' => $content['message'] ?? '',
            'actions' => $content['actions'] ?? [],
            'icon' => $content['icon'] ?? null,
            'color' => $content['color'] ?? 'blue',
            'data' => $content['data'] ?? [],
        ];
    }

    /**
     * Get relative time for display
     */
    public function getRelativeTimeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}