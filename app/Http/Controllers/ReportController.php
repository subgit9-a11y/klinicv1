<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Reports\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request)
    {
        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->startOfMonth();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        $data = $this->reports->dashboard($from, $to);

        return view('reports.index', [
            'data' => $data,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
