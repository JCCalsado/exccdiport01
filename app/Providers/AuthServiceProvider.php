<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\StudentFeePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => StudentFeePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}