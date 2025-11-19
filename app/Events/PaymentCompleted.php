<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Payment $payment,
        public array $additionalData = []
    ) {}

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
        return 'payment.completed';
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
            'payment_method' => $this->payment->payment_method,
            'paid_at' => $this->payment->paid_at?->toISOString(),
            'receipt_number' => $this->payment->receipt_number,
            'additional_data' => $this->additionalData,
            'timestamp' => now()->toISOString(),
        ];
    }
}