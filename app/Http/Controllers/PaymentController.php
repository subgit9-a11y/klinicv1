<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['invoice', 'patient', 'collectedBy'])->latest('paid_at');

        if ($request->filled('method')) {
            $query->where('method', $request->input('method'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->paginate(25)->appends($request->only(['method', 'status']));

        $totals = (object) [
            'collected_cents' => Payment::where('status', 'SUCCESS')->sum('amount_cents'),
            'count' => Payment::where('status', 'SUCCESS')->count(),
        ];

        return view('payments.index', ['payments' => $payments, 'totals' => $totals]);
    }
}
