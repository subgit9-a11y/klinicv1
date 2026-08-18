<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentOrder;
use App\Services\Payments\CashfreePaymentProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcilePayments extends Command
{
    protected $signature = 'klinic:reconcile-payments';

    protected $description = 'Re-verify pending payment orders against the gateway and settle stale ones';

    public function handle(CashfreePaymentProvider $provider): int
    {
        if (! $provider->isConfigured()) {
            $this->info('Payment gateway not configured — skipping reconciliation.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subHours(6);

        $orders = PaymentOrder::query()
            ->where('status', 'PENDING')
            ->where('gateway', 'CASHFREE')
            ->where('created_at', '<', $cutoff)
            ->limit(100)
            ->get();

        $settled = 0;
        foreach ($orders as $order) {
            $result = $provider->verify($order->gateway_order_id);

            if (($result['success'] ?? false)) {
                $order->update(['status' => 'PAID']);
                $settled++;
            }
        }

        $this->info("Reconciled {$settled} of {$orders->count()} pending payment orders.");

        return self::SUCCESS;
    }
}
