<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Services\AssetReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetReportController extends Controller
{
    public function __construct(private AssetReportService $reportService)
    {
        $this->middleware('permission:asset report view')->only('index');
    }

    public function index(Request $request): View
    {
        $allCategories = ItemCategory::query()->orderBy('name')->get();

        if ($request->has('categories')) {
            $selectedCategoryIds = array_map('intval', (array) $request->input('categories', []));
        } else {
            // Default: pilih semua kategori aktif
            $selectedCategoryIds = $allCategories->pluck('id')->all();
        }

        // Bangun data laporan aset
        $report = $this->reportService->build($selectedCategoryIds);

        return view('asset-reports.index', [
            'report' => $report,
            'allCategories' => $allCategories,
            'selectedCategoryIds' => $selectedCategoryIds,
        ]);
    }
}
