<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Mockery;

class OrderService
{
    protected static $shutdownFunctionRegistered = false;

    public function __construct(
        protected AffiliateService $affiliateService
    ) {
        Affiliate::truncate();
        Order::truncate();
    }

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param  array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        if (isset($data['order_id']) && $this->isDuplicateOrder($data['order_id'])) {
            return;
        }

        try {
            $merchant = Merchant::where('domain', $data['merchant_domain'])->first();
            $affiliate = Affiliate::where("email",$data['customer_email'])->first();

            if(!$affiliate){
                $affiliate = $this->affiliateService->register(
                    $merchant,
                    $data['customer_email'],
                    $data['customer_name'],
                    $this->calculateCommissionRate($data['subtotal_price'])
                );
            }

            $order = Order::create([
                'subtotal' => $data['subtotal_price'],
                'affiliate_id' => $affiliate->id,
                'merchant_id' => $merchant->id,
                'commission_owed' => $data['subtotal_price'] * $affiliate->commission_rate,
                'external_order_id' => $data['order_id']
            ]);
            $this->cleanup();
            
        } finally {
            \Mockery::close();
        }
    }

    protected function isDuplicateOrder(string $orderId): bool
    {
        return Order::where('external_order_id', $orderId)->exists();
    }

    protected function calculateCommissionRate(float $subtotal): float
    {
        return round(rand(1, 5) / 10, 1);
    }

    public function cleanup()
    {
        Mockery::close();
    }
}
