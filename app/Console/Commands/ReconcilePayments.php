<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentOrder;
use App\Services\Payments\CashfreePaymentProvider;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcilePayments extends Command
{
    protected $signature = 'klinic:reconcile-payments';

    protected $description = 'Re-verify open payment orders against the gateway, settle verified ones, expire stale ones';

    public function handle(CashfreePaymentProvider $provider, PaymentSettlementService $settlement): int
    {
        if (! $provider->isConfigured()) {
            $this->info('Payment gateway not configured — skipping reconciliation.');

            return self::SUCCESS;
        }

        // Orders the webhook may have missed. Covers the full open vocabulary
        // (CREATED = checkout link issued, PENDING = attempt seen, not yet
        // verified) — not just one status.
        $orders = PaymentOrder::query()
            ->whereIn('status', [PaymentOrder::STATUS_CREATED, PaymentOrder::STATUS_PENDING])
            ->where('gateway', 'CASHFREE')
            ->where('created_at', '<', Carbon::now()->subHours(1))
            ->limit(100)
            ->get();

        $settled = 0;
        $expired = 0;
        $ttlHours = (int) config('klinic.payments.order_ttl_hours', 48);
        $expiryCutoff = Carbon::now()->subHours($ttlHours);

        foreach ($orders as $order) {
            if ($order->gateway_order_id === null) {
                continue;
            }

            $result = $provider->verify($order->gateway_order_id);

            if (($result['verified'] ?? false)) {
                $outcome = $settlement->settle($order, $result);
                if ($outcome['settled']) {
                    $settled++;
                }

                continue;
            }

            // Gateway says not paid and the order is past its TTL — a customer
            // can no longer complete it. Expire it so reporting is accurate.
            if ($order->created_at->lt($expiryCutoff)) {
                $order->expire();
                $expired++;
            }
        }

        $this->info("Reconciled {$settled} settled / {$expired} expired of {$orders->count()} open payment orders.");

        return self::SUCCESS;
    }
}
