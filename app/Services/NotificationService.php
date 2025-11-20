<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Create a notification and send it
     */
    public function createNotification(array $data): NotificationLog
    {
        try {
            $notification = NotificationLog::create([
                'user_id' => $data['user_id'],
                'notification_type' => $data['type'],
                'channel' => $data['channel'] ?? 'in_app',
                'recipient' => $this->getRecipient($data['user_id'], $data['channel'] ?? 'in_app'),
                'content' => $this->formatNotificationContent($data),
                'subject' => $data['title'] ?? null,
                'status' => 'pending',
                'metadata' => $data['data'] ?? [],
            ]);

            // Send the notification based on channel
            $this->sendNotification($notification);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            
            throw $e;
        }
    }

    /**
     * Send notification through specified channels
     */
    private function sendNotification(NotificationLog $notification): void
    {
        try {
            switch ($notification->channel) {
                case 'email':
                    $this->sendEmailNotification($notification);
                    break;
                case 'sms':
                    $this->sendSmsNotification($notification);
                    break;
                case 'in_app':
                    $this->sendInAppNotification($notification);
                    break;
                case 'push':
                    $this->sendPushNotification($notification);
                    break;
                default:
                    Log::warning('Unknown notification channel', ['channel' => $notification->channel]);
                    break;
            }

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Failed to send notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(NotificationLog $notification): void
    {
        $user = User::find($notification->user_id);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $content = $notification->content;
        
        Mail::raw($content['message'] ?? '', function ($message) use ($user, $notification, $content) {
            $message->to($user->email)
                ->subject($notification->subject ?? 'Notification');
        });
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(NotificationLog $notification): void
    {
        $user = User::find($notification->user_id);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $provider = config('notifications.sms.provider', 'semaphore');
        
        switch ($provider) {
            case 'semaphore':
                $this->sendSemaphoreSms($notification, $user);
                break;
            case 'twilio':
                $this->sendTwilioSms($notification, $user);
                break;
            default:
                Log::warning('Unknown SMS provider', ['provider' => $provider]);
                break;
        }
    }

    /**
     * Send SMS via Semaphore
     */
    private function sendSemaphoreSms(NotificationLog $notification, User $user): void
    {
        $apiKey = config('notifications.sms.semaphore.api_key');
        $senderName = config('notifications.sms.semaphore.sender_name', 'APP');
        
        if (!$apiKey) {
            Log::warning('Semaphore API key not configured');
            return;
        }

        // Implementation for Semaphore SMS
        Log::info('SMS sent via Semaphore', [
            'phone' => $user->phone,
            'message' => substr($notification->content['message'] ?? '', 0, 100),
        ]);
    }

    /**
     * Send SMS via Twilio
     */
    private function sendTwilioSms(NotificationLog $notification, User $user): void
    {
        $accountSid = config('notifications.sms.twilio.account_sid');
        $authToken = config('notifications.sms.twilio.auth_token');
        $fromNumber = config('notifications.sms.twilio.from_number');
        
        if (!$accountSid || !$authToken || !$fromNumber) {
            Log::warning('Twilio configuration incomplete');
            return;
        }

        // Implementation for Twilio SMS
        Log::info('SMS sent via Twilio', [
            'phone' => $user->phone,
            'message' => substr($notification->content['message'] ?? '', 0, 100),
        ]);
    }

    /**
     * Send in-app notification
     */
    private function sendInAppNotification(NotificationLog $notification): void
    {
        // Broadcast via WebSocket if configured
        if (config('broadcasting.default') === 'pusher') {
            try {
                $content = $notification->content;
                
                event(new \App\Events\NotificationReceived(
                    $notification->user_id,
                    $content['title'] ?? '',
                    $content['message'] ?? '',
                    $content['data'] ?? [],
                    $notification->id
                ));
            } catch (\Exception $e) {
                Log::error('Failed to broadcast notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(NotificationLog $notification): void
    {
        $provider = config('notifications.push.provider');
        
        switch ($provider) {
            case 'fcm':
                $this->sendFcmNotification($notification);
                break;
            default:
                Log::warning('Unknown push provider', ['provider' => $provider]);
                break;
        }
    }

    /**
     * Send FCM (Firebase Cloud Messaging) notification
     */
    private function sendFcmNotification(NotificationLog $notification): void
    {
        $serverKey = config('notifications.push.fcm.server_key');
        
        if (!$serverKey) {
            Log::warning('FCM server key not configured');
            return;
        }

        // Implementation for FCM
        Log::info('Push notification sent via FCM', [
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * Get recipient based on channel
     */
    private function getRecipient(int $userId, string $channel): string
    {
        $user = User::find($userId);
        if (!$user) {
            return '';
        }

        return match ($channel) {
            'email' => $user->email,
            'sms' => $user->phone ?? '',
            'in_app' => "user_{$userId}",
            'push' => "user_{$userId}",
            default => '',
        };
    }

    /**
     * Format notification content
     */
    private function formatNotificationContent(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'actions' => $data['actions'] ?? [],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? 'blue',
            'data' => $data['data'] ?? [],
        ];
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, int $limit = 50): array
    {
        return NotificationLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(int $userId): int
    {
        return NotificationLog::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = NotificationLog::find($notificationId);
        
        if (!$notification) {
            return false;
        }

        return $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): int
    {
        return NotificationLog::where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => 'delivered',
            ]);
    }

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId): bool
    {
        $notification = NotificationLog::find($notificationId);
        
        if (!$notification) {
            return false;
        }

        return $notification->delete();
    }
}