<?php

namespace App\Http\Controllers;

use App\Models\ServiceTicket;
use Illuminate\View\View;

class ServiceTicketTrackingController extends Controller
{
    public function show(string $token): View
    {
        $ticket = ServiceTicket::query()
            ->with(['customer', 'statusLogs' => fn ($q) => $q->oldest()])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return view('service-tickets.track', [
            'ticket' => $ticket,
            'statuses' => config('service-tickets.statuses'),
        ]);
    }
}
