<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPaymentController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Payments/Index');
    }

    public function revenue()
    {
        return Inertia::render('Admin/Payments/Revenue');
    }

    public function show(Payment $payment)
    {
        return Inertia::render('Admin/Payments/Show', ['paymentId' => $payment->id]);
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
