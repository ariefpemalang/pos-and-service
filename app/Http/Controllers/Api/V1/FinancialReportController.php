<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    use RespondsWithApi;

    public function __construct(private FinancialReportService $reportService)
    {
        $this->middleware('permission:financial report view')->only('show');
    }

    public function show(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $report = $this->reportService->build($from, $to);

        $report['commissions'] = collect($report['commissions'])->values()->all();
        $report['manual_income']['by_category'] = collect($report['manual_income']['by_category'])->values()->all();
        $report['manual_expense']['by_category'] = collect($report['manual_expense']['by_category'])->values()->all();

        return $this->apiSuccess('Laporan keuangan berhasil diambil.', $report);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
