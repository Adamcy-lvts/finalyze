<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Inertia\Inertia;

class AdminAuditController extends Controller
{
    public function index()
    {
        $logs = AdminAuditLog::with('admin')
            ->latest()
            ->paginate(50)
            ->through(fn ($log) => [
                'id' => $log->id,
                'admin' => $log->admin?->only('id', 'name', 'email'),
                'action' => $log->action,
                'description' => $log->description,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
        ]);
    }
}
