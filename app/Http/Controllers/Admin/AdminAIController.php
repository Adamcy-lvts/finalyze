<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AdminAIController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/AI/Index');
    }

    public function queue()
    {
        return Inertia::render('Admin/AI/Queue');
    }

    public function failures()
    {
        return Inertia::render('Admin/AI/Failures');
    }

    public function retry($generation)
    {
        return response()->json(['status' => 'ok']);
    }

    public function resetCircuit($service)
    {
        return response()->json(['status' => 'ok']);
    }
}
