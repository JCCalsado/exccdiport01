<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Redirect students to their specialized dashboard
        if ($user->role->value === 'student') {
            return redirect()->route('student.dashboard');
        }

        // For admin/accounting, show general dashboard
        $notifications = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('target_role', $user->role->value)
                  ->orWhere('target_role', 'all');
            })
            ->orderByDesc('start_date')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'notifications' => $notifications,
            'auth' => [
                'user' => $user,
            ],
        ]);
    }
}