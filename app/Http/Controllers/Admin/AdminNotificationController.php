<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Inertia\Inertia;

class AdminNotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::latest()->take(50)->get();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(AdminNotification $notification)
    {
        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back();
    }

    public function markAllRead()
    {
        AdminNotification::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back();
    }
}
