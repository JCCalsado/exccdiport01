<?php

namespace App\Listeners;

use App\Events\PaymentStatusChanged;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Services\NotificationService;
use App\Enums\UserRoleEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentCompleted as PaymentCompletedMail;
use App\Mail\PaymentFailed as PaymentFailedMail;
use App\Mail\AdminPaymentCompleted as AdminPaymentCompletedMail;
use App\Mail\AdminPaymentFailed as AdminPaymentFailedMail;

class SendPaymentNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Handle payment status change events
     */
    public function handle($event): void
    {
        try {
            if ($event instanceof PaymentCompleted) {
                $this->sendPaymentCompletedNotification($event->payment);
            } elseif ($event instanceof PaymentFailed) {
                $this->sendPaymentFailedNotification($event->payment, $event->reason);
            } elseif ($event instanceof PaymentStatusChanged) {
                $this->sendPaymentStatusChangeNotification($event);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send payment notification', [
                'event_class' => get_class($event),
                'payment_id' => $event->payment->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send payment completed notification
     */
    private function sendPaymentCompletedNotification($payment): void
    {
        $student = $payment->student;
        $user = $student?->user;

        if (!$user) {
            Log::warning('Cannot send payment notification: Student user not found', [
                'payment_id' => $payment->id,
                'student_id' => $payment->student_id,
            ]);
            return;
        }

        // Send email notification
        if (config('app.notifications.payment_confirmation', true)) {
            try {
                Mail::to($user->email)->send(new PaymentCompletedMail($payment));
                Log::info('Payment confirmation email sent', [
                    'payment_id' => $payment->id,
                    'email' => $user->email,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send payment confirmation email', [
                    'payment_id' => $payment->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send in-app notification
        $this->notificationService->createNotification([
            'user_id' => $user->id,
            'type' => 'payment_completed',
            'title' => 'Payment Completed',
            'message' => "Your payment of ₱{$payment->amount} has been successfully processed. Receipt #: {$payment->reference_number}",
            'data' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'reference_number' => $payment->reference_number,
                'payment_method' => $payment->payment_method,
            ],
        ]);

        // Send SMS notification if configured
        if (config('app.notifications.sms_confirmation', false) && $student->phone) {
            $this->sendSmsNotification($student->phone, $this->getPaymentCompletedSmsMessage($payment));
        }

        // Notify administrators
        $this->notifyAdministrators($payment, 'payment_completed');
    }

    /**
     * Send payment failed notification
     */
    private function sendPaymentFailedNotification($payment, string $reason): void
    {
        $student = $payment->student;
        $user = $student?->user;

        if (!$user) {
            Log::warning('Cannot send payment failure notification: Student user not found', [
                'payment_id' => $payment->id,
                'student_id' => $payment->student_id,
            ]);
            return;
        }

        // Send email notification
        if (config('app.notifications.payment_failure', true)) {
            try {
                Mail::to($user->email)->send(new PaymentFailedMail($payment, $reason));
                Log::info('Payment failure email sent', [
                    'payment_id' => $payment->id,
                    'email' => $user->email,
                    'reason' => $reason,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send payment failure email', [
                    'payment_id' => $payment->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Send in-app notification
        $this->notificationService->createNotification([
            'user_id' => $user->id,
            'type' => 'payment_failed',
            'title' => 'Payment Failed',
            'message' => "Your payment of ₱{$payment->amount} has failed. Reason: {$reason}",
            'data' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'reason' => $reason,
                'payment_method' => $payment->payment_method,
            ],
        ]);

        // Notify administrators
        $this->notifyAdministrators($payment, 'payment_failed', $reason);
    }

    /**
     * Send payment status change notification
     */
    private function sendPaymentStatusChangeNotification(PaymentStatusChanged $event): void
    {
        // Skip if already handled by specific events
        if (in_array($event->newStatus, ['completed', 'failed'])) {
            return;
        }

        $student = $event->payment->student;
        $user = $student?->user;

        if (!$user) {
            return;
        }

        // Send in-app notification for status changes
        $statusMessages = [
            'pending' => 'Your payment is being processed.',
            'cancelled' => 'Your payment has been cancelled.',
            'refunded' => 'Your payment has been refunded.',
        ];

        $message = $statusMessages[$event->newStatus] ?? "Payment status updated to {$event->newStatus}.";

        $this->notificationService->createNotification([
            'user_id' => $user->id,
            'type' => 'payment_status_change',
            'title' => 'Payment Status Updated',
            'message' => $message,
            'data' => [
                'payment_id' => $event->payment->id,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'amount' => $event->payment->amount,
            ],
        ]);
    }

    /**
     * Notify administrators about payment events
     */
    private function notifyAdministrators($payment, string $eventType, string $reason = ''): void
    {
        if (!config('app.notifications.admin_notifications', true)) {
            return;
        }

        $adminEmails = config('app.notifications.admin_emails', []);

        foreach ($adminEmails as $email) {
            try {
                switch ($eventType) {
                    case 'payment_completed':
                        Mail::to($email)->send(new AdminPaymentCompletedMail($payment));
                        break;
                    case 'payment_failed':
                        Mail::to($email)->send(new AdminPaymentFailedMail($payment, $reason));
                        break;
                }
            } catch (\Exception $e) {
                Log::error('Failed to send admin payment notification', [
                    'email' => $email,
                    'event_type' => $eventType,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Create admin notifications
        $adminUsers = \App\Models\User::whereIn('role', [UserRoleEnum::ADMIN, UserRoleEnum::ACCOUNTING])->get();

        foreach ($adminUsers as $admin) {
            $this->notificationService->createNotification([
                'user_id' => $admin->id,
                'type' => 'admin_payment_' . $eventType,
                'title' => 'Payment ' . ucfirst(str_replace('_', ' ', $eventType)),
                'message' => $this->getAdminNotificationMessage($payment, $eventType, $reason),
                'data' => [
                    'payment_id' => $payment->id,
                    'student_id' => $payment->student_id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                ],
            ]);
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(string $phoneNumber, string $message): void
    {
        try {
            // Implement SMS service integration here
            // For example: Twilio, Semaphore, etc.

            Log::info('SMS notification sent', [
                'phone' => $phoneNumber,
                'message' => substr($message, 0, 100) . '...', // Log truncated message
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send SMS notification', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get payment completed SMS message
     */
    private function getPaymentCompletedSmsMessage($payment): string
    {
        return "Payment of ₱{$payment->amount} completed successfully. Receipt #: {$payment->reference_number}. Thank you!";
    }

    /**
     * Get admin notification message
     */
    private function getAdminNotificationMessage($payment, string $eventType, string $reason = ''): string
    {
        $student = $payment->student;
        $studentName = $student ? $student->user->name : 'Unknown Student';

        switch ($eventType) {
            case 'payment_completed':
                return "{$studentName} completed a payment of ₱{$payment->amount} via {$payment->payment_method}.";
            case 'payment_failed':
                return "{$studentName}'s payment of ₱{$payment->amount} failed. Reason: {$reason}";
            default:
                return "{$studentName}'s payment status changed to {$eventType}.";
        }
    }
}