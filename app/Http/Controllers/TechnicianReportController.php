<?php

namespace App\Http\Controllers;

use App\Services\TechnicianReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicianReportController extends Controller
{
    public function __construct(private TechnicianReportService $reportService)
    {
        $this->middleware('permission:technician report view')->only(['index', 'exportExcel']);
    }

    public function index(Request $request): View
    {
        $mode = $request->input('mode', 'monthly'); // 'monthly' or 'yearly'
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        if ($mode === 'yearly') {
            $data = $this->reportService->getYearlyReport($year);
        } else {
            $mode = 'monthly';
            $data = $this->reportService->getMonthlyReport($year, $month);
        }

        return view('technician-reports.index', [
            'mode' => $mode,
            'year' => $year,
            'month' => $month,
            'data' => $data,
            'monthsList' => TechnicianReportService::MONTH_NAMES,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $mode = $request->input('mode', 'monthly');
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return $this->reportService->exportExcel($mode, $year, $month);
    }
}
