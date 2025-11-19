<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

use App\Events\PaymentStatusChanged;
use App\Events\PaymentInitiated;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Listeners\UpdatePaymentStatus;
use App\Listeners\SendPaymentNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Payment Events
        PaymentInitiated::class => [
            // Listeners for payment initiation can be added here
        ],

        PaymentCompleted::class => [
            UpdatePaymentStatus::class,
            SendPaymentNotification::class,
        ],

        PaymentFailed::class => [
            SendPaymentNotification::class,
        ],

        PaymentStatusChanged::class => [
            UpdatePaymentStatus::class,
            SendPaymentNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}