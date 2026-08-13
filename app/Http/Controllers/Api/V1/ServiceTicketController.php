<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServiceTicketResource;
use App\Models\BankAccount;
use App\Models\ServiceTicket;
use App\Models\Technician;
use App\Services\ServiceTicketService;
use App\Services\SettingService;
use App\Support\PaymentMethodResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ServiceTicketController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct(private ServiceTicketService $tickets)
    {
        $this->middleware('permission:service ticket view')->only(['index', 'show', 'options', 'claimSlip']);
        $this->middleware('permission:service ticket create')->only('store');
        $this->middleware('permission:service ticket edit')->only(['update', 'updateStatus', 'updateWarranty', 'resendNotification']);
        $this->middleware('permission:service ticket delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);
        $search = trim((string) $request->query('search', ''));

        $query = ServiceTicket::query()
            ->with(['customer:id,code,name,phone', 'technician:id,name', 'paidTransaction', 'downPaymentTransaction'])
            ->latest('created_at');

        $this->applyIndexFilters($query, $request);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('ticket_no', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('serial_imei', 'like', "%{$search}%")
                    ->orWhere('complaint', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customer) use ($search) {
                        $customer->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query->paginate($perPage);

        return $this->apiSuccess('Daftar tiket servis berhasil diambil.', [
            'items' => ServiceTicketResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
            'summary' => $this->ticketSummary(),
        ]);
    }

    public function options(): JsonResponse
    {
        return $this->apiSuccess('Opsi tiket servis berhasil diambil.', [
            'statuses' => collect(config('service-tickets.statuses', []))
                ->map(fn ($label, $key) => [
                    'key' => (string) $key,
                    'label' => (string) $label,
                    'color' => (string) config("service-tickets.status_colors.{$key}", 'secondary'),
                    'closed' => in_array($key, config('service-tickets.closed_statuses', []), true),
                ])
                ->values(),
            'device_types' => collect(config('service-tickets.device_types', []))
                ->map(fn ($label, $key) => ['key' => (string) $key, 'label' => (string) $label])
                ->values(),
            'warranty_presets' => collect(config('service-tickets.warranty_presets', []))
                ->map(fn ($label, $days) => ['days' => (int) $days, 'label' => (string) $label])
                ->values(),
            'warranty_default_days' => (int) config('service-tickets.warranty_default_days', 30),
            'warranty_max_days' => (int) config('service-tickets.warranty_max_days', 365),
            'payment_methods' => collect(config('workshop.payment_methods', []))
                ->map(fn ($label, $key) => ['key' => (string) $key, 'label' => (string) $label])
                ->values(),
            'technicians' => Technician::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Technician $t) => ['id' => $t->id, 'name' => $t->name])
                ->values(),
            'bank_accounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('bank_name')
                ->get()
                ->map(fn (BankAccount $a) => [
                    'id' => $a->id,
                    'label' => $a->displayLabel(),
                    'bank_name' => $a->bank_name,
                    'account_name' => $a->account_name,
                    'account_number' => $a->account_number,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedDetails($request);
        $notifyCustomer = $request->boolean('notify_customer');

        try {
            $result = $this->tickets->create($validated, (int) $request->user()->id, $notifyCustomer);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        $ticket = $this->loadDetail($result['ticket']);

        return $this->apiSuccess(
            $this->notifyMessage('Tiket servis berhasil dibuat.', $notifyCustomer, $result['notify']),
            [
                'ticket' => new ServiceTicketResource($ticket),
                'notify' => $result['notify'],
            ],
            201,
        );
    }

    public function show(ServiceTicket $serviceTicket): JsonResponse
    {
        return $this->apiSuccess('Detail tiket berhasil diambil.', [
            'ticket' => new ServiceTicketResource($this->loadDetail($serviceTicket)),
        ]);
    }

    public function update(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        $validated = $this->validatedDetails($request);
        $validated['updated_at'] = $request->input('updated_at');

        try {
            $ticket = $this->tickets->updateDetails($serviceTicket, $validated, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), $this->errorStatus($e));
        }

        return $this->apiSuccess('Data tiket servis diperbarui.', [
            'ticket' => new ServiceTicketResource($this->loadDetail($ticket)),
        ]);
    }

    public function updateStatus(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(config('service-tickets.statuses')))],
            'note' => ['nullable', 'string', 'max:1000'],
            'notify_customer' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->tickets->updateStatus(
                $serviceTicket,
                $validated['status'],
                $validated['note'] ?? null,
                (int) $request->user()->id,
                $request->boolean('notify_customer', true),
            );
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        return $this->apiSuccess(
            $this->notifyMessage(
                'Status tiket diubah menjadi '.$result['ticket']->statusLabel().'.',
                $request->boolean('notify_customer', true),
                $result['notify'],
            ),
            [
                'ticket' => new ServiceTicketResource($this->loadDetail($result['ticket'])),
                'notify' => $result['notify'],
            ],
        );
    }

    public function updateWarranty(Request $request, ServiceTicket $serviceTicket): JsonResponse
    {
        try {
            $ticket = $this->tickets->updateWarranty($serviceTicket, $this->validatedWarranty($request));
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        return $this->apiSuccess('Data garansi diperbarui.', [
            'ticket' => new ServiceTicketResource($this->loadDetail($ticket)),
        ]);
    }

    public function resendNotification(ServiceTicket $serviceTicket): JsonResponse
    {
        $notify = $this->tickets->resendNotification($serviceTicket);

        if (! $notify['notified']) {
            return $this->apiError('Gagal kirim WhatsApp: '.($notify['error'] ?? 'tidak diketahui'), 422);
        }

        return $this->apiSuccess('Notifikasi WhatsApp dikirim ulang ke pelanggan.', [
            'ticket' => new ServiceTicketResource($this->loadDetail($serviceTicket->fresh())),
            'notify' => $notify,
        ]);
    }

    public function claimSlip(ServiceTicket $serviceTicket, SettingService $settings): JsonResponse
    {
        $ticket = $serviceTicket->load([
            'customer',
            'technician',
            'downPaymentTransaction.bankAccount',
        ]);
        $dp = $ticket->downPaymentTransaction;
        $dpAmount = $dp ? (float) $dp->total : $ticket->downPaymentAmount();

        return $this->apiSuccess('Bukti pengambilan berhasil diambil.', [
            'app_name' => $settings->getString('app_name', 'AR Service'),
            'ticket' => new ServiceTicketResource($ticket),
            'slip' => [
                'ticket_no' => $ticket->ticket_no,
                'received_at' => $ticket->created_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
                'customer_name' => $ticket->customer?->name,
                'customer_phone' => $ticket->customer?->phone,
                'device' => $ticket->deviceSummary().($ticket->color ? ' · '.$ticket->color : ''),
                'serial_imei' => $ticket->serial_imei,
                'accessories' => $ticket->accessories,
                'complaint' => $ticket->complaint,
                'estimated_cost' => $ticket->estimated_cost !== null ? (float) $ticket->estimated_cost : null,
                'down_payment' => $dpAmount,
                'remaining' => $ticket->estimated_cost !== null
                    ? round(max(0, (float) $ticket->estimated_cost - $dpAmount), 2)
                    : null,
                'payment_label' => $dp
                    ? PaymentMethodResolver::label($dp->payment_method)
                    : PaymentMethodResolver::label($ticket->down_payment_payment_method),
            ],
        ]);
    }

    public function destroy(ServiceTicket $serviceTicket): JsonResponse
    {
        try {
            $this->tickets->deleteTicket($serviceTicket);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        return $this->apiSuccess('Tiket servis dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDetails(Request $request): array
    {
        $isNewCustomer = $request->input('customer_id') === '__new__'
            || $request->boolean('new_customer');

        $validated = $request->validate([
            'customer_id' => $isNewCustomer
                ? ['nullable']
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
            ...$this->warrantyRules($request),
        ]);

        $validated['technician_id'] = $validated['technician_id'] ?? null;
        $validated['estimated_cost'] = isset($validated['estimated_cost']) ? round((float) $validated['estimated_cost'], 2) : null;
        $validated['down_payment'] = round((float) ($validated['down_payment'] ?? 0), 2);
        $validated = $this->applyWarrantyToValidated($validated, $request);

        if ($validated['down_payment'] <= 0) {
            $validated['down_payment'] = 0;
            $validated['down_payment_payment_method'] = null;
            $validated['down_payment_bank_account_id'] = null;
        } else {
            $validated['down_payment_bank_account_id'] = ($validated['down_payment_payment_method'] ?? null) === 'transfer'
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
        return $this->applyWarrantyToValidated($request->validate($this->warrantyRules($request)), $request);
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
    private function applyWarrantyToValidated(array $validated, Request $request): array
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
     * @param  Builder<ServiceTicket>  $query
     */
    private function applyIndexFilters(Builder $query, Request $request): void
    {
        $status = $request->string('status')->toString();
        $closed = config('service-tickets.closed_statuses', ['completed', 'cancelled']);

        if ($status === 'open') {
            $query->whereNotIn('status', $closed);
        } elseif (array_key_exists($status, config('service-tickets.statuses', []))) {
            $query->where('status', $status);
        }

        $technicianId = $request->integer('technician_id');
        if ($technicianId > 0) {
            $query->where('technician_id', $technicianId);
        }

        $deviceType = $request->string('device_type')->toString();
        if (array_key_exists($deviceType, config('service-tickets.device_types', []))) {
            $query->where('device_type', $deviceType);
        }

        $warranty = $request->string('warranty')->toString();
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

    private function loadDetail(ServiceTicket $ticket): ServiceTicket
    {
        return $ticket->load([
            'customer:id,code,name,phone',
            'technician:id,name',
            'user:id,name',
            'downPaymentBankAccount',
            'paidTransaction',
            'downPaymentTransaction',
            'statusLogs' => fn ($q) => $q->latest()->with('user:id,name'),
        ]);
    }

    /**
     * @param  array{notified: bool, hiwa_job_id: string|null, error: string|null}  $notify
     */
    private function notifyMessage(string $success, bool $wanted, array $notify): string
    {
        if (! $wanted) {
            return $success;
        }
        if ($notify['notified']) {
            return $success.' WhatsApp terkirim ke pelanggan.';
        }

        return $success.' WhatsApp belum terkirim: '.($notify['error'] ?? 'tidak diketahui');
    }

    private function errorStatus(InvalidArgumentException $e): int
    {
        return str_contains($e->getMessage(), 'orang lain') ? 409 : 422;
    }
}
