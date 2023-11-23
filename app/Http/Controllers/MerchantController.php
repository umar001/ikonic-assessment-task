<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Order;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;


class MerchantController extends Controller
{
    public function __construct(
        MerchantService $merchantService
    ) {}

    /**
     * Useful order statistics for the merchant API.
     * 
     * @param Request $request Will include a from and to date
     * @return JsonResponse Should be in the form {count: total number of orders in range, commission_owed: amount of unpaid commissions for orders with an affiliate, revenue: sum order subtotals}
     */
    public function orderStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $from = $request->input('from');
        $to = $request->input('to');

        $orders = Order::whereBetween('created_at', [$from, $to])->get();
        $noAffiliate = Order::where('created_at', '>', now()->subDay())
            ->where('created_at', '<=', now())
            ->where('affiliate_id', null)
            ->first();

        $response = [
            'count' => $orders->count(),
            'revenue' => $orders->sum('subtotal'),
            'commissions_owed' => $orders->sum('commission_owed') - $noAffiliate->commission_owed,
        ];

        return response()->json($response);
    }
}
