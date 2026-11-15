<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ServiceTicket;
use App\Models\Technician;
use App\Services\ServiceTicketService;
use App\Support\MoneyInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Yajra\DataTables\Facades\DataTables;

class ServiceTicketController extends Controller
{
    public function __construct(private ServiceTicketService $tickets)
    {
        $this->middleware('permission:service ticket view')->only(['index', 'show', 'claimSlip']);
        $this->middleware('permission:service ticket create')->only(['create', 'store']);
        $this->middleware('permission:service ticket edit')->only(['edit', 'update', 'updateStatus', 'updateWorkLines', 'updateWarranty', 'resendNotification', 'pickupCancelled', 'updateTechnician']);
        $this->middleware('permission:service ticket delete')->only('destroy');
    }

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            $query = ServiceTicket::query()
                ->with(['customer', 'technician', 'paidTransaction'])
                ->latest();

            $this->applyIndexFilters($query);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer_name', fn (ServiceTicket $t) => $t->customer?->name ?? '-')
                ->addColumn('device', fn (ServiceTicket $t) => $t->deviceSummary())
                ->addColumn('status_label', fn (ServiceTicket $t) => '<span class="badge text-bg-'.$t->statusColor().'">'.$t->statusLabel().'</span>')
                ->addColumn('technician_name', fn (ServiceTicket $t) => $t->technician?->name ?? '-')
                ->addColumn('created_at', fn (ServiceTicket $t) => $t->created_at?->format('d/m/Y H:i'))
                ->addColumn('action', fn (ServiceTicket $t) => view('service-tickets.include.action', ['ticket' => $t])->render())
                ->rawColumns(['status_label', 'action'])
                ->with('summary', $this->ticketSummary())
                ->toJson();
        }

        $defaultStatus = request()->has('status') ? request()->string('status')->toString() : 'received';

        return view('service-tickets.index', [
            'summary' => $this->ticketSummary(),
            'statusFilters' => $this->statusFilterItems(),
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'deviceTypes' => config('service-tickets.device_types'),
            'filters' => [
                'status' => $defaultStatus,
                'warranty' => request()->string('warranty')->toString(),
                'technician_id' => request()->integer('technician_id') ?: '',
                'device_type' => request()->string('device_type')->toString(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('service-tickets.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedDetails($request);
        $notifyCustomer = $request->boolean('notify_customer');

        $result = $this->tickets->create($validated, (int) $request->user()->id, $notifyCustomer);
        $redirect = redirect()->route('service-tickets.show', $result['ticket']);

        if ($result['notify']['notified']) {
            return $redirect->with('success', 'Tiket servis dibuat dan notifikasi WhatsApp dikirim ke pelanggan.');
        }

        if ($notifyCustomer) {
            return $redirect
                ->with('success', 'Tiket servis berhasil dibuat.')
                ->with('warning', 'WhatsApp belum terkirim: '.($result['notify']['error'] ?? 'tidak diketahui').'. Pelanggan tetap bisa dilacak lewat tautan di detail tiket.');
        }

        return $redirect->with('success', 'Tiket servis berhasil dibuat.');
    }

    public function show(ServiceTicket $serviceTicket): View
    {
        $serviceTicket->load([
            'customer',
            'technician',
            'user',
            'downPaymentBankAccount',
            'paidTransaction',
            'downPaymentTransaction.bankAccount',
            'statusLogs' => fn ($q) => $q->latest()->with('user'),
            'itemLines',
            'serviceLines',
        ]);

        $selectable = array_fill_keys(
            (array) config('service-tickets.selectable_statuses', []),
            null
        );

        $loggedInTechnician = request()->user()?->resolveTechnician();
        if (! $serviceTicket->technician_id && $loggedInTechnician) {
            $serviceTicket->update(['technician_id' => $loggedInTechnician->id]);
            $serviceTicket->load('technician');
        }

        return view('service-tickets.show', [
            'ticket'   => $serviceTicket,
            'statuses' => array_intersect_key(config('service-tickets.statuses', []), $selectable),
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'specialty']),
            'loggedInTechnician' => $loggedInTechnician,
            'catalogItems' => \App\Models\Item::query()
                ->with(['category:id,name', 'unit:id,name,abbreviation'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'photo', 'category_id', 'unit_id', 'stock',
                    'purchase_price', 'selling_price',
                ]),
            'catalogServices' => \App\Models\WorkshopService::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'price']),
        ]);
    }

    public function claimSlip(ServiceTicket $serviceTicket): View
    {
        $serviceTicket->load([
            'customer',
            'technician',
            'downPaymentTransaction.bankAccount',
        ]);

        return view('service-tickets.claim-slip', [
            'ticket' => $serviceTicket,
            'transaction' => $serviceTicket->downPaymentTransaction,
        ]);
    }

    public function pickupSlip(ServiceTicket $serviceTicket): View
    {
        $serviceTicket->load([
            'customer',
            'technician',
            'user',
            'itemLines.item',
            'serviceLines',
            'paidTransaction.items',
            'downPaymentTransaction.bankAccount',
            'statusLogs.user',
        ]);

        return view('service-tickets.pickup-slip', [
            'ticket' => $serviceTicket,
            'transaction' => $serviceTicket->paidTransaction ?? $serviceTicket->downPaymentTransaction,
        ]);
    }

    public function edit(ServiceTicket $serviceTicket): View
    {
        if ($serviceTicket->isClosed()) {
            abort(403, 'Tiket yang sudah selesai atau dibatalkan tidak bisa diubah.');
        }

        return view('service-tickets.edit', [
            'ticket' => $serviceTicket,
            ...$this->formData(),
        ]);
    }

    public function update(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        try {
            $this->tickets->updateDetails($serviceTicket, $this->validatedDetails($request), (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('service-tickets.show', $serviceTicket)
            ->with('success', 'Data tiket servis diperbarui.');
    }

    public function updateWarranty(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        try {
            $this->tickets->updateWarranty($serviceTicket, $this->validatedWarranty($request));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('service-tickets.show', $serviceTicket)
            ->with('success', 'Data garansi diperbarui.');
    }

    public function updateStatus(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(config('service-tickets.statuses')))],
            'note' => ['nullable', 'string', 'max:1000'],
            'notify_customer' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.workshop_service_id' => ['required_with:services', 'integer', 'exists:workshop_services,id'],
            'services.*.quantity' => ['required_with:services', 'integer', 'min:1'],
            'services.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $result = $this->tickets->updateStatus(
                $serviceTicket,
                $validated['status'],
                $validated['note'] ?? null,
                (int) $request->user()->id,
                $request->boolean('notify_customer', true),
                $validated['items'] ?? [],
                $validated['services'] ?? [],
            );
            if ($request->filled('technician_id')) {
                $result['ticket']->update(['technician_id' => $request->integer('technician_id')]);
            }
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $redirect = redirect()->route('service-tickets.show', $result['ticket'])
            ->with('success', 'Status tiket diubah menjadi '.$result['ticket']->statusLabel().'.');

        if ($request->boolean('notify_customer', true) && ! $result['notify']['notified']) {
            $redirect->with('warning', 'WhatsApp belum terkirim: '.($result['notify']['error'] ?? 'tidak diketahui'));
        }

        return $redirect;
    }

    public function updateWorkLines(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.workshop_service_id' => ['required_with:services', 'integer', 'exists:workshop_services,id'],
            'services.*.quantity' => ['required_with:services', 'integer', 'min:1'],
            'services.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $ticket = $this->tickets->updateWorkLines(
                $serviceTicket,
                $validated['items'] ?? [],
                $validated['services'] ?? [],
            );
            if ($request->filled('technician_id')) {
                $ticket->update(['technician_id' => $request->integer('technician_id')]);
            }
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('transactions.create', ['ticket' => $ticket->id]);
    }

    public function pickupCancelled(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        if ($serviceTicket->status !== 'cancelled') {
            return back()->with('error', 'Hanya perangkat yang berstatus dibatalkan yang dapat diambil tanpa pembayaran.');
        }

        try {
            $this->tickets->markCancelledAsPickedUp($serviceTicket, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('service-tickets.show', $serviceTicket)
            ->with('success', 'Perangkat berhasil diserahkan kepada pelanggan (Status: Sudah diambil).');
    }

    public function updateTechnician(Request $request, ServiceTicket $serviceTicket): RedirectResponse
    {
        $validated = $request->validate([
            'technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
        ]);

        $serviceTicket->update([
            'technician_id' => $validated['technician_id'] ?: null,
        ]);

        return back()->with('success', 'Teknisi servis berhasil diperbarui.');
    }

    public function resendNotification(ServiceTicket $serviceTicket): RedirectResponse
    {
        $notify = $this->tickets->resendNotification($serviceTicket);

        if ($notify['notified']) {
            return back()->with('success', 'Notifikasi WhatsApp dikirim ulang ke pelanggan.');
        }

        return back()->with('error', 'Gagal kirim WhatsApp: '.($notify['error'] ?? 'tidak diketahui'));
    }

    public function destroy(ServiceTicket $serviceTicket): RedirectResponse
    {
        $isSuperAdmin = (bool) auth()->user()?->hasRole('Super Admin');

        if (! $isSuperAdmin && ! in_array($serviceTicket->status, ['received', 'cancelled'], true)) {
            return back()->with('error', 'Hanya Super Admin yang dapat menghapus tiket yang sudah diambil atau selesai.');
        }

        try {
            $this->tickets->deleteTicket($serviceTicket, $isSuperAdmin);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('service-tickets.index')
            ->with('success', 'Tiket servis berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDetails(Request $request): array
    {
        $isNewCustomer = $request->input('customer_id') === '__new__';

        $request->merge([
            'estimated_cost' => MoneyInput::parse($request->input('estimated_cost')),
            'down_payment' => MoneyInput::parse($request->input('down_payment')) ?? 0,
        ]);

        $validated = $request->validate([
            'customer_id' => $isNewCustomer
                ? ['required', 'in:__new__']
                : ['required', 'exists:customers,id'],
            'new_customer_name' => [$isNewCustomer ? 'required' : 'nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:30'],
            'new_customer_address' => ['nullable', 'string', 'max:500'],
            'technician_id' => ['nullable', 'exists:technicians,id'],
            'device_type' => ['required', Rule::in(array_keys(config('service-tickets.device_types')))],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'max:50'],
            'serial_imei' => ['nullable', 'string', 'max:80'],
            'specs' => ['nullable', 'string', 'max:2000'],
            'accessories' => ['nullable', 'string', 'max:1000'],
            'complaint' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'down_payment_payment_method' => [
                Rule::requiredIf(fn () => (float) $request->input('down_payment') > 0),
                'nullable',
                'in:cash,qris,transfer',
            ],
            'down_payment_bank_account_id' => [
                'nullable',
                'required_if:down_payment_payment_method,transfer',
                'exists:bank_accounts,id',
            ],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['technician_id'] = $validated['technician_id'] ?? null;
        $validated['estimated_cost'] = $validated['estimated_cost'] ?? null;
        $validated['down_payment'] = round((float) ($validated['down_payment'] ?? 0), 2);

        if ($validated['down_payment'] <= 0) {
            $validated['down_payment'] = 0;
            $validated['down_payment_payment_method'] = null;
            $validated['down_payment_bank_account_id'] = null;
        } else {
            $validated['down_payment_bank_account_id'] = $validated['down_payment_payment_method'] === 'transfer'
                ? ($validated['down_payment_bank_account_id'] ?? null)
                : null;
        }

        if ($isNewCustomer) {
            $validated['new_customer'] = [
                'name' => $validated['new_customer_name'],
                'phone' => $validated['new_customer_phone'] ?? null,
                'address' => $validated['new_customer_address'] ?? null,
            ];
            unset($validated['customer_id']);
        }

        unset(
            $validated['new_customer_name'],
            $validated['new_customer_phone'],
            $validated['new_customer_address'],
        );

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedWarranty(Request $request): array
    {
        $validated = $request->validate($this->warrantyRules($request));

        return $this->applyWarrantyToValidated($validated);
    }

    /**
     * @return array<string, mixed>
     */
    private function warrantyRules(Request $request): array
    {
        $presetKeys = array_map(strval(...), array_keys(config('service-tickets.warranty_presets', [])));

        return [
            'warranty_preset' => ['nullable', Rule::in([...$presetKeys, 'custom'])],
            'warranty_days' => [
                Rule::requiredIf(fn () => $request->input('warranty_preset') === 'custom'),
                'nullable',
                'integer',
                'min:1',
                'max:'.(int) config('service-tickets.warranty_max_days', 365),
            ],
            'warranty_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyWarrantyToValidated(array $validated): array
    {
        $preset = $validated['warranty_preset'] ?? (string) config('service-tickets.warranty_default_days', 30);

        if ($preset === 'custom') {
            $validated['warranty_days'] = (int) ($validated['warranty_days'] ?? 0);
        } else {
            $validated['warranty_days'] = (int) $preset;
        }

        $validated['warranty_notes'] = filled($validated['warranty_notes'] ?? null)
            ? trim((string) $validated['warranty_notes'])
            : null;

        unset($validated['warranty_preset']);

        return $validated;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ServiceTicket>  $query
     */
    private function applyIndexFilters($query): void
    {
        $status = request()->string('status')->toString();
        $closed = config('service-tickets.closed_statuses', ['completed', 'cancelled']);

        if ($status === 'open') {
            $query->whereNotIn('status', $closed);
        } elseif (array_key_exists($status, config('service-tickets.statuses', []))) {
            $query->where('status', $status);
        }

        $technicianId = request()->integer('technician_id');
        if ($technicianId > 0) {
            $query->where('technician_id', $technicianId);
        }

        $deviceType = request()->string('device_type')->toString();
        if (array_key_exists($deviceType, config('service-tickets.device_types', []))) {
            $query->where('device_type', $deviceType);
        }

        $warranty = request()->string('warranty')->toString();
        if ($warranty === 'none') {
            $query->where('warranty_days', 0);
        } elseif ($warranty === 'pending') {
            $query->where('warranty_days', '>', 0)
                ->whereNull('completed_at')
                ->where('status', '!=', 'cancelled');
        } elseif ($warranty === 'active') {
            $query->where('warranty_days', '>', 0)
                ->whereNotNull('completed_at')
                ->where('status', '!=', 'cancelled')
                ->whereRaw('DATE_ADD(completed_at, INTERVAL warranty_days DAY) >= ?', [now()]);
        } elseif ($warranty === 'expired') {
            $query->where('warranty_days', '>', 0)
                ->whereNotNull('completed_at')
                ->where('status', '!=', 'cancelled')
                ->whereRaw('DATE_ADD(completed_at, INTERVAL warranty_days DAY) < ?', [now()]);
        }
    }

    /**
     * @return array{all: int, open: int, statuses: array<string, int>}
     */
    private function ticketSummary(): array
    {
        $counts = ServiceTicket::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = [];
        foreach (array_keys(config('service-tickets.statuses', [])) as $status) {
            $statuses[$status] = (int) ($counts[$status] ?? 0);
        }

        $closed = config('service-tickets.closed_statuses', ['completed', 'cancelled']);
        $open = 0;
        foreach ($statuses as $status => $total) {
            if (! in_array($status, $closed, true)) {
                $open += $total;
            }
        }

        return [
            'all' => array_sum($statuses),
            'open' => $open,
            'statuses' => $statuses,
        ];
    }

    /**
     * @return list<array{key: string, label: string, color: string}>
     */
    private function statusFilterItems(): array
    {
        $items = [];

        $selectable = (array) config('service-tickets.filter_statuses', config('service-tickets.selectable_statuses', []));
        $allLabels  = config('service-tickets.statuses', []);

        foreach ($selectable as $key) {
            $items[] = [
                'key'   => $key,
                'label' => (string) ($allLabels[$key] ?? $key),
                'color' => (string) config("service-tickets.status_colors.{$key}", 'secondary'),
            ];
        }

        return $items;
    }

    /**
     * @return array{customers: \Illuminate\Support\Collection, technicians: \Illuminate\Support\Collection, deviceTypes: array<string, string>}
     */
    private function formData(): array
    {
        return [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'code', 'name', 'phone']),
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'deviceTypes' => config('service-tickets.device_types'),
            'warrantyPresets' => config('service-tickets.warranty_presets'),
            'bankAccounts' => \App\Models\BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
        ];
    }
}
