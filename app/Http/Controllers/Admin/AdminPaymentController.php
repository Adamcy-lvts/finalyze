<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('user')
            ->latest()
            ->paginate(20)
            ->through(fn ($p) => [
                'id' => $p->id,
                'user' => $p->user?->only('id', 'name', 'email'),
                'amount' => $p->amount / 100,
                'status' => $p->status,
                'channel' => $p->channel,
                'paid_at' => $p->paid_at,
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function revenue()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        $summary = [
            'today' => Payment::successful()->whereDate('paid_at', $today)->sum('amount') / 100,
            'week' => Payment::successful()->where('paid_at', '>=', $thisWeek)->sum('amount') / 100,
            'month' => Payment::successful()->where('paid_at', '>=', $thisMonth)->sum('amount') / 100,
            'all_time' => Payment::successful()->sum('amount') / 100,
        ];

        return Inertia::render('Admin/Payments/Revenue', [
            'summary' => $summary,
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load('user');

        return Inertia::render('Admin/Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'user' => $payment->user?->only('id', 'name', 'email'),
                'amount' => $payment->amount / 100,
                'status' => $payment->status,
                'channel' => $payment->channel,
                'paid_at' => $payment->paid_at,
                'verified_at' => $payment->verified_at,
                'metadata' => $payment->metadata,
            ],
        ]);
    }

    public function verify(Payment $payment)
    {
        return response()->json(['status' => 'ok']);
    }

    public function refund(Payment $payment)
    {
        return response()->json(['status' => 'ok']);
    }

    public function manualCredit(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }
}
