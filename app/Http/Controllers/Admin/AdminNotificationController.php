<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Inertia\Inertia;

class AdminNotificationController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Notifications/Index');
    }

    public function markRead(AdminNotification $notification)
    {
        return response()->json(['status' => 'ok']);
    }

    public function markAllRead()
    {
        return response()->json(['status' => 'ok']);
    }
}
