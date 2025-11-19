<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Models\NotificationLog;
use App\Models\User;

class NotificationService
{
    /**
     * Create and send a notification
     */
    public function createNotification(array $data): NotificationLog
    {
        $notification = NotificationLog::create([
            'user_id' => $data['user_id'] ?? null,
            'notification_type' => $data['type'],
            'channel' => $data['channel'] ?? 'in_app',
            'recipient' => $data['recipient'] ?? $this->getRecipient($data),
            'content' => $this->prepareContent($data),
            'subject' => $data['subject'] ?? null,
            'metadata' => $data['data'] ?? [],
        ]);

        // Send the notification
        $this->sendNotification($notification);

        return $notification;
    }

    /**
     * Send notification through appropriate channel
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
                    Log::warning('Unsupported notification channel', [
                        'notification_id' => $notification->id,
                        'channel' => $notification->channel,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel,
                'error' => $e->getMessage(),
            ]);

            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(NotificationLog $notification): void
    {
        $recipient = $notification->recipient;
        $user = $notification->user;

        if (!$recipient) {
            throw new \Exception('No email recipient specified');
        }

        try {
            // Send email using appropriate template based on notification type
            $mailable = $this->getMailableClass($notification);

            if ($mailable) {
                Mail::to($recipient)->send($mailable);
            } else {
                // Generic email
                Mail::raw($notification->content['message'] ?? 'Notification', function ($message) use ($notification, $recipient) {
                    $message->to($recipient)
                           ->subject($notification->subject ?? 'Notification');
                });
            }

            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Email notification sent', [
                'notification_id' => $notification->id,
                'recipient' => $recipient,
                'type' => $notification->notification_type,
            ]);

        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(NotificationLog $notification): void
    {
        $phoneNumber = $notification->recipient;
        $message = $notification->content['message'] ?? '';

        if (!$phoneNumber || !$message) {
            throw new \Exception('Phone number or message not specified');
        }

        try {
            // Integrate with SMS provider (Twilio, Semaphore, etc.)
            $result = $this->sendSmsViaProvider($phoneNumber, $message);

            if ($result['success']) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                Log::info('SMS notification sent', [
                    'notification_id' => $notification->id,
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                    'type' => $notification->notification_type,
                ]);
            } else {
                throw new \Exception($result['message'] ?? 'SMS send failed');
            }

        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send in-app notification
     */
    private function sendInAppNotification(NotificationLog $notification): void
    {
        // Store notification for real-time display
        // This will be retrieved by the frontend via WebSocket or polling

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Broadcast to user's private channel if using websockets
        if (config('broadcasting.default') !== 'null') {
            try {
                broadcast(new \App\Events\NotificationSent($notification))->toOthers();
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('In-app notification created', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->notification_type,
        ]);
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(NotificationLog $notification): void
    {
        $user = $notification->user;

        if (!$user) {
            throw new \Exception('User not specified for push notification');
        }

        try {
            // Get user's device tokens
            $deviceTokens = $this->getUserDeviceTokens($user);

            if (empty($deviceTokens)) {
                throw new \Exception('No device tokens found for user');
            }

            // Send push notification via Firebase FCM or other service
            $result = $this->sendPushNotificationViaService($deviceTokens, $notification);

            if ($result['success']) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                Log::info('Push notification sent', [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'devices_count' => count($deviceTokens),
                ]);
            } else {
                throw new \Exception($result['message'] ?? 'Push notification failed');
            }

        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get recipient based on notification data
     */
    private function getRecipient(array $data): ?string
    {
        if (isset($data['recipient'])) {
            return $data['recipient'];
        }

        if (isset($data['user_id'])) {
            $user = User::find($data['user_id']);
            return match ($data['channel'] ?? 'in_app') {
                'email' => $user?->email,
                'sms' => $user?->student?->contact_number,
                'push' => $user?->id, // For push, we use user_id
                default => null,
            };
        }

        return null;
    }

    /**
     * Prepare notification content
     */
    private function prepareContent(array $data): array
    {
        return [
            'title' => $data['title'] ?? '',
            'message' => $data['message'] ?? '',
            'data' => $data['data'] ?? [],
            'actions' => $data['actions'] ?? [],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] => $this->getNotificationColor($data['type'] ?? 'info'),
        ];
    }

    /**
     * Get notification color based on type
     */
    private function getNotificationColor(string $type): string
    {
        return match ($type) {
            'payment_completed', 'success' => 'green',
            'payment_failed', 'error', 'warning' => 'red',
            'warning' => 'yellow',
            'info', 'default' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get appropriate mailable class for notification type
     */
    private function getMailableClass(NotificationLog $notification): ?\Illuminate\Mail\Mailable
    {
        $mailables = [
            'payment_completed' => \App\Mail\PaymentCompleted::class,
            'payment_failed' => \App\Mail\PaymentFailed::class,
            'assessment_created' => \App\Mail\AssessmentCreated::class,
            'account_balance_low' => \App\Mail\AccountBalanceLow::class,
        ];

        $mailableClass = $mailables[$notification->notification_type] ?? null;

        if ($mailableClass && class_exists($mailableClass)) {
            // The mailable should be instantiated with appropriate data
            return new $mailableClass($notification);
        }

        return null;
    }

    /**
     * Send SMS via provider
     */
    private function sendSmsViaProvider(string $phoneNumber, string $message): array
    {
        $provider = config('notifications.sms.provider', 'semaphore');

        return match ($provider) {
            'semaphore' => $this->sendViaSemaphore($phoneNumber, $message),
            'twilio' => $this->sendViaTwilio($phoneNumber, $message),
            'debug' => $this->sendSmsDebug($phoneNumber, $message),
            default => ['success' => false, 'message' => 'Unknown SMS provider'],
        };
    }

    /**
     * Send SMS via Semaphore (Philippines)
     */
    private function sendViaSemaphore(string $phoneNumber, string $message): array
    {
        $apiKey = config('notifications.sms.semaphore.api_key');
        $senderName = config('notifications.sms.semaphore.sender_name', 'SCHOOL');

        if (!$apiKey) {
            return ['success' => false, 'message' => 'Semaphore API key not configured'];
        }

        $response = Http::asForm()->post('https://api.semaphore.co/api/v4/messages', [
            'apikey' => $apiKey,
            'number' => $phoneNumber,
            'message' => $message,
            'sendername' => $senderName,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => isset($data[0]['message_id']),
                'message' => $data[0]['message'] ?? 'SMS sent successfully',
                'message_id' => $data[0]['message_id'] ?? null,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to send SMS via Semaphore',
            'response' => $response->body(),
        ];
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio(string $phoneNumber, string $message): array
    {
        $accountSid = config('notifications.sms.twilio.account_sid');
        $authToken = config('notifications.sms.twilio.auth_token');
        $fromNumber = config('notifications.sms.twilio.from_number');

        if (!$accountSid || !$authToken || !$fromNumber) {
            return ['success' => false, 'message' => 'Twilio credentials not configured'];
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $phoneNumber,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'SMS sent via Twilio'];
            }

            return [
                'success' => false,
                'message' => 'Twilio API error: ' . $response->json('message', 'Unknown error'),
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Twilio exception: ' . $e->getMessage()];
        }
    }

    /**
     * Debug SMS sending (logs instead of actually sending)
     */
    private function sendSmsDebug(string $phoneNumber, string $message): array
    {
        Log::info('SMS Debug Mode', [
            'phone' => $this->maskPhoneNumber($phoneNumber),
            'message' => $message,
        ]);

        return ['success' => true, 'message' => 'SMS logged in debug mode'];
    }

    /**
     * Send push notification via service
     */
    private function sendPushNotificationViaService(array $deviceTokens, NotificationLog $notification): array
    {
        $provider = config('notifications.push.provider', 'fcm');

        return match ($provider) {
            'fcm' => $this->sendViaFCM($deviceTokens, $notification),
            'debug' => $this->sendPushDebug($deviceTokens, $notification),
            default => ['success' => false, 'message' => 'Unknown push provider'],
        };
    }

    /**
     * Send push notification via Firebase Cloud Messaging
     */
    private function sendViaFCM(array $deviceTokens, NotificationLog $notification): array
    {
        $serverKey = config('notifications.push.fcm.server_key');

        if (!$serverKey) {
            return ['success' => false, 'message' => 'FCM server key not configured'];
        }

        $payload = [
            'registration_ids' => $deviceTokens,
            'notification' => [
                'title' => $notification->content['title'],
                'body' => $notification->content['message'],
                'icon' => $notification->content['icon'] ?? 'default',
                'color' => $notification->content['color'] ?? 'blue',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'data' => array_merge(
                $notification->content['data'] ?? [],
                [
                    'notification_id' => $notification->id,
                    'type' => $notification->notification_type,
                    'title' => $notification->content['title'],
                    'body' => $notification->content['message'],
                ]
            ),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);

        $data = $response->json();

        if ($response->successful() && ($data['success'] ?? 0) > 0) {
            return [
                'success' => true,
                'message' => "Push notification sent to {$data['success']} devices",
                'failed_count' => $data['failure'] ?? 0,
            ];
        }

        return [
            'success' => false,
            'message' => 'FCM error: ' . ($data['results'][0]['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * Debug push notification
     */
    private function sendPushDebug(array $deviceTokens, NotificationLog $notification): array
    {
        Log::info('Push Notification Debug Mode', [
            'notification_id' => $notification->id,
            'device_count' => count($deviceTokens),
            'title' => $notification->content['title'],
            'message' => $notification->content['message'],
        ]);

        return ['success' => true, 'message' => 'Push notification logged in debug mode'];
    }

    /**
     * Get user device tokens for push notifications
     */
    private function getUserDeviceTokens(User $user): array
    {
        // In a real implementation, this would retrieve device tokens from a user_devices table
        // For now, return empty array
        return [];

        // Example implementation:
        // return $user->devices()->where('active', true)->pluck('device_token')->toArray();
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        if (strlen($phoneNumber) <= 4) {
            return '****';
        }

        return substr($phoneNumber, 0, 2) . '****' . substr($phoneNumber, -2);
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(User $user, array $filters = [])
    {
        $query = NotificationLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if (isset($filters['type'])) {
            $query->where('notification_type', $filters['type']);
        }

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['unread'])) {
            $query->whereNull('read_at');
        }

        return $query->paginate(20);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(NotificationLog $notification): bool
    {
        return $notification->update([
            'read_at' => now(),
            'status' => 'delivered',
        ]);
    }

    /**
     * Mark all user notifications as read
     */
    public function markAllAsRead(User $user): int
    {
        return NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Get unread notification count for user
     */
    public function getUnreadCount(User $user): int
    {
        return NotificationLog::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}