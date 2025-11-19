<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Payment $payment,
        public string $oldStatus,
        public string $newStatus,
        public array $metadata = []
    ) {
        $this->oldStatus = $oldStatus ?? $payment->getOriginal('status', 'pending');
        $this->newStatus = $newStatus ?? $payment->status;
        $this->metadata = array_merge($metadata, [
            'timestamp' => now()->toISOString(),
            'student_id' => $payment->student_id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('student.' . $this->payment->student_id),
            new PrivateChannel('admin.payments'),
            new PrivateChannel('accounting.payments'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'reference_number' => $this->payment->reference_number,
            'student_id' => $this->payment->student_id,
            'amount' => $this->payment->amount,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'payment_method' => $this->payment->payment_method,
            'gateway_status' => $this->payment->latestGatewayDetail?->gateway_status,
            'metadata' => $this->metadata,
        ];
    }
}