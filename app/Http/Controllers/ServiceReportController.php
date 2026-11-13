<?php

namespace App\Http\Controllers;

use App\Services\ServiceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceReportController extends Controller
{
    public function __construct(private ServiceReportService $reportService)
    {
        $this->middleware('permission:service report view')->only(['index', 'exportExcel']);
    }

    public function index(Request $request): View
    {
        $mode = $request->input('mode', 'yearly'); // 'yearly' or 'monthly'
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($mode === 'monthly') {
            $data = $this->reportService->getMonthlyReport($year, $month);
        } else {
            $mode = 'yearly';
            $data = $this->reportService->getYearlyReport($year);
        }

        return view('service-reports.index', [
            'mode' => $mode,
            'year' => $year,
            'month' => $month,
            'data' => $data,
            'monthsList' => ServiceReportService::MONTH_NAMES,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $mode = $request->input('mode', 'yearly');
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return $this->reportService->exportExcel($mode, $year, $month);
    }
}
