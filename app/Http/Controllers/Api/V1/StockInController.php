<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ManagesStockBatches;
use App\Http\Controllers\Controller;
use App\Services\StockService;

class StockInController extends Controller
{
    use ManagesStockBatches;

    public function __construct(private StockService $stockService)
    {
        $this->middleware('permission:stock in view')->only(['index', 'show']);
        $this->middleware('permission:stock in create')->only('store');
    }

    protected function stockType(): string
    {
        return 'in';
    }

    protected function stockService(): StockService
    {
        return $this->stockService;
    }
}
