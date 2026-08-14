<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Support\PermissionGroups;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(): View
    {
        PermissionGroups::syncToDatabase();

        return view('dashboard', $this->dashboard->build());
    }
}
