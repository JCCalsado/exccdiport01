<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $notifications = Notification::query()
            ->where(function ($q) use ($user) {
                $q->where('target_role', $user->role)
                  ->orWhere('target_role', 'all');
            })
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'role' => $user->role,
        ]);
    }

    // Accounting: create new notification (payables / schedules)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'message'     => 'nullable|string',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'target_role' => 'required|string|in:student,accounting,admin,all',
        ]);

        Notification::create($validated);

        return redirect()->back()->with('success', 'Notification created.');
    }
}