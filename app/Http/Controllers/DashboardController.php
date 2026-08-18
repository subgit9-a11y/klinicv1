<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Reports\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index()
    {
        $user = Auth::user();

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfDay();

        // Super admins (no tenant) see aggregate totals across tenants;
        // tenant users see only their clinic via TenantContext middleware.
        $stats = $this->reports->dashboard($from, $to);

        return view('dashboard', [
            'period' => $stats['period'],
            'stats' => [
                [
                    'label' => __('klinic360.nav.patients'),
                    'value' => $this->formatNumber($stats['patients']['acquisition']['new_patients'] ?? 0),
                    'color' => 'bg-blue-50 text-blue-700',
                    'sub' => __('klinic360.dashboard_stats.new_this_period'),
                ],
                [
                    'label' => __('klinic360.nav.appointments'),
                    'value' => $this->formatNumber($stats['operational']['appointments']['total'] ?? 0),
                    'color' => 'bg-green-50 text-green-700',
                    'sub' => __('klinic360.dashboard_stats.completed', ['count' => $stats['operational']['appointments']['completed'] ?? 0]),
                ],
                [
                    'label' => __('klinic360.nav.queue'),
                    'value' => $this->formatNumber($stats['operational']['appointments']['checked_in'] ?? 0),
                    'color' => 'bg-yellow-50 text-yellow-700',
                    'sub' => __('klinic360.dashboard_stats.in_consultation', ['count' => $stats['operational']['appointments']['in_consultation'] ?? 0]),
                ],
                [
                    'label' => __('klinic360.nav.billing'),
                    'value' => $this->formatCurrency($stats['financial']['revenue']['total_collected'] ?? 0),
                    'color' => 'bg-brand-50 text-brand-700',
                    'sub' => __('klinic360.dashboard_stats.outstanding', ['amount' => $this->formatCurrency($stats['financial']['revenue']['total_outstanding'] ?? 0)]),
                ],
            ],
        ]);
    }

    private function formatNumber(int $n): string
    {
        return number_format($n);
    }

    private function formatCurrency(float $amount): string
    {
        return '₹'.number_format($amount, 2);
    }
}
