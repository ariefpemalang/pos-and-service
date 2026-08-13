<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockReportController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:stock report view')->only('show');
    }

    public function show(Request $request): JsonResponse
    {
        $query = Item::query()->with(['category:id,name', 'unit:id,name,abbreviation']);

        $filter = (string) $request->query('filter', '');
        if ($filter === 'low_stock') {
            $query->lowStock();
        } elseif ($filter === 'out_of_stock') {
            $query->where('is_active', true)->where('stock', 0);
        }

        $paginator = $this->paginateQuery(
            $request,
            $query,
            ['name', 'code'],
            [],
        );

        $items = collect($paginator->items())->map(function (Item $item) {
            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'photo' => $item->photo,
                'photo_url' => $item->absolutePhotoUrl(),
                'stock' => (int) $item->stock,
                'stock_opname' => (int) $item->stock_opname,
                'selling_price' => (float) $item->selling_price,
                'is_active' => (bool) $item->is_active,
                'is_low_stock' => $item->isLowStock(),
                'status' => $this->statusOf($item),
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

        return $this->apiSuccess('Laporan stok berhasil diambil.', [
            'summary' => [
                'total_items' => Item::count(),
                'low_stock' => Item::lowStock()->count(),
                'out_of_stock' => Item::query()->where('is_active', true)->where('stock', 0)->count(),
                'total_stock_value' => (float) (Item::query()->selectRaw('COALESCE(SUM(stock * purchase_price), 0) as total')->value('total') ?? 0),
            ],
            'filter' => $filter !== '' ? $filter : null,
            'items' => $items,
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    private function statusOf(Item $item): string
    {
        if (! $item->is_active) {
            return 'inactive';
        }

        if ($item->isLowStock()) {
            return 'low';
        }

        return 'safe';
    }
}
