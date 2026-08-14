<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\DashboardService;
use App\Support\PermissionGroups;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use RespondsWithApi;

    public function __construct(private DashboardService $dashboard)
    {
        $this->middleware('permission:dashboard view')->only('show');
    }

    public function show(): JsonResponse
    {
        PermissionGroups::syncToDatabase();

        $payload = $this->dashboard->build();

        $lowStock = collect($payload['low_stock_items'])->map(function (Item $item) {
            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'photo' => $item->photo,
                'photo_url' => $item->absolutePhotoUrl(),
                'stock' => (int) $item->stock,
                'stock_opname' => (int) $item->stock_opname,
                'category' => $item->category
                    ? ['id' => $item->category->id, 'name' => $item->category->name]
                    : null,
                'unit' => $item->unit
                    ? [
                        'id' => $item->unit->id,
                        'name' => $item->unit->name,
                        'abbreviation' => $item->unit->abbreviation,
                    ]
                    : null,
            ];
        })->values();

        $recent = collect($payload['recent_transactions'])->map(function (Transaction $tx) {
            return [
                'id' => $tx->id,
                'transaction_no' => $tx->transaction_no,
                'type' => $tx->type,
                'customer_name' => $tx->displayCustomerName(),
                'total' => (float) $tx->total,
                'created_at' => $tx->created_at?->toIso8601String(),
            ];
        })->values();

        return $this->apiSuccess('Dashboard berhasil diambil.', [
            'month_label' => now()->translatedFormat('F Y'),
            'kpis' => $payload['kpis'],
            'charts' => $payload['charts'],
            'low_stock_items' => $lowStock,
            'recent_transactions' => $recent,
        ]);
    }
}
