<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsToModal;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ServiceTicket;
use App\Models\Technician;
use App\Models\Transaction;
use App\Models\WorkshopService;
use App\Services\TransactionService;
use App\Support\DateRangeFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    use RespondsToModal;

    public function __construct(private TransactionService $transactionService)
    {
        $this->middleware('permission:transaction view')->only(['index', 'show', 'invoice', 'itemAvailability']);
        $this->middleware('permission:transaction create')->only(['create', 'store']);
        $this->middleware('permission:transaction edit')->only(['edit', 'update']);
        $this->middleware('permission:transaction delete')->only(['destroy']);
    }

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            [$from, $to] = DateRangeFilter::resolve(request());
            $sort = request('sort', 'tanggal_desc');

            $query = Transaction::query()
                ->with(['customer:id,code,name', 'technician:id,code,name', 'user:id,name', 'bankAccount']);

            match ($sort) {
                'tanggal_asc' => $query->orderBy('created_at', 'asc'),
                'total_desc' => $query->orderBy('total', 'desc'),
                'total_asc' => $query->orderBy('total', 'asc'),
                default => $query->orderBy('created_at', 'desc'),
            };

            DateRangeFilter::apply($query, $from, $to);

            $technicianId = request()->integer('technician_id');
            if ($technicianId > 0) {
                $query->where('technician_id', $technicianId);
            }

            return DataTables::of($query)
                ->order(function ($q) use ($sort) {
                    match ($sort) {
                        'tanggal_asc' => $q->orderBy('created_at', 'asc'),
                        'total_desc' => $q->orderBy('total', 'desc'),
                        'total_asc' => $q->orderBy('total', 'asc'),
                        default => $q->orderBy('created_at', 'desc'),
                    };
                })
                ->addIndexColumn()
                ->addColumn('type_label', fn (Transaction $t) => match (true) {
                    $t->isDownPayment() && $t->isRefunded() => '<span class="badge bg-warning-subtle text-warning">UMK dikembalikan</span>',
                    $t->isDownPayment() => '<span class="badge bg-success-subtle text-success">Uang muka</span>',
                    $t->type === 'sale' => '<span class="badge bg-primary-subtle text-primary">Penjualan</span>',
                    $t->type === 'service' => '<span class="badge bg-info-subtle text-info">Servis</span>',
                    $t->type === 'combined' => '<span class="badge bg-warning-subtle text-warning">Gabungan</span>',
                    default => e($t->type),
                })
                ->addColumn('status_label', fn (Transaction $t) => match ($t->status) {
                    'cancelled' => '<span class="badge bg-danger-subtle text-danger">Batal</span>',
                    default => '<span class="badge bg-success-subtle text-success">Selesai</span>',
                })
                ->addColumn('customer_name', fn (Transaction $t) => e($t->displayCustomerName()))
                ->addColumn('technician_name', fn (Transaction $t) => e($t->technician?->name ?? '-'))
                ->addColumn('total_fmt', fn (Transaction $t) => 'Rp '.number_format((float) $t->total, 0, ',', '.'))
                ->addColumn('payment_label', fn (Transaction $t) => $this->paymentBadge($t))
                ->addColumn('commission_fmt', fn (Transaction $t) => $t->technician_commission > 0
                    ? 'Rp '.number_format((float) $t->technician_commission, 0, ',', '.')
                    : '-')
                ->addColumn('user_name', fn (Transaction $t) => e($t->user?->name ?? '-'))
                ->addColumn('created_at', fn (Transaction $t) => $t->created_at?->format('d/m/Y H:i'))
                ->addColumn('action', 'transactions.include.action')
                ->rawColumns(['action', 'type_label', 'status_label', 'payment_label'])
                ->toJson();
        }

        $filters = DateRangeFilter::forView(request());
        $filters['sort'] = request('sort', 'tanggal_desc');

        return view('transactions.index', [
            'filters' => $filters,
            'technicians' => Technician::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $ticket = $this->resolveBillingTicket($request);

        if ($request->filled('ticket') && ! $ticket) {
            return redirect()
                ->route('transactions.create')
                ->with('error', 'Tiket servis tidak ditemukan.');
        }

        if ($ticket && ! $ticket->canCreateInvoice()) {
            return redirect()
                ->route('service-tickets.show', $ticket)
                ->with('error', 'Nota bayar hanya bisa dibuat jika status tiket Bisa diambil dan belum ada nota pelunasan.');
        }

        return view('transactions.create', [
            'ticket' => $ticket,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'code', 'name', 'phone']),
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'commission_percent']),
            'items' => Item::query()
                ->with(['category:id,name', 'unit:id,name,abbreviation'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'photo', 'category_id', 'unit_id', 'stock',
                    'purchase_price', 'selling_price',
                ]),
            'services' => WorkshopService::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'price']),
            'techPercent' => (int) config('workshop.default_technician_commission_percent', 20),
            'ownerPercent' => 100 - (int) config('workshop.default_technician_commission_percent', 20),
            'bankAccounts' => \App\Models\BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $this->validateTransactionPayload($request, requirePayment: true);

        try {
            $transaction = $this->transactionService->create($validated, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->modalError($e->getMessage());
        }

        if ($request->expectsJson()) {
            return $this->modalSuccess(
                'Transaksi berhasil disimpan.',
                ['transaction_no' => $transaction->transaction_no, 'id' => $transaction->id]
            );
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaksi '.$transaction->transaction_no.' berhasil disimpan.');
    }

    public function itemAvailability(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $items = Item::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get(['id', 'code', 'name', 'stock', 'selling_price'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'stock' => (int) $item->stock,
                'selling_price' => (float) $item->selling_price,
            ]);

        return response()->json(['data' => $items]);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load([
            'customer',
            'technician',
            'user:id,name',
            'items',
            'serviceLines',
            'bankAccount',
            'serviceTicket',
        ]);

        return response()->json(['data' => $transaction]);
    }

    public function invoice(Transaction $transaction): View
    {
        abort_unless($transaction->isCompleted(), 404);

        $transaction->load([
            'customer',
            'technician',
            'user:id,name',
            'items',
            'serviceLines',
            'bankAccount',
            'serviceTicket.customer',
            'serviceTicket.technician',
            'serviceTicket.downPaymentTransaction.bankAccount',
        ]);

        if ($transaction->isDownPayment() && $transaction->serviceTicket) {
            return view('service-tickets.claim-slip', [
                'ticket' => $transaction->serviceTicket,
                'transaction' => $transaction,
            ]);
        }

        return view('transactions.invoice', compact('transaction'));
    }

    public function edit(Transaction $transaction): View
    {
        abort_unless($transaction->isCompleted(), 404);

        if ($transaction->isDownPayment()) {
            abort(403, 'Transaksi uang muka tidak bisa diubah. Ubah dari tiket servis.');
        }

        $transaction->load(['items', 'serviceLines', 'customer', 'technician', 'bankAccount', 'serviceTicket']);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'technicians' => Technician::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'commission_percent']),
            'items' => Item::query()
                ->with(['category:id,name', 'unit:id,name,abbreviation'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'photo', 'category_id', 'unit_id', 'stock',
                    'purchase_price', 'selling_price',
                ]),
            'services' => WorkshopService::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'price']),
            'bankAccounts' => \App\Models\BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
            'stockCredit' => $transaction->items
                ->mapWithKeys(fn ($line) => [(int) $line->item_id => (int) $line->quantity])
                ->all(),
            'initialItems' => $transaction->items->map(fn ($line) => [
                'item_id' => $line->item_id,
                'code' => $line->item_code,
                'name' => $line->item_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ])->values(),
            'initialServices' => $transaction->serviceLines->map(fn ($line) => [
                'workshop_service_id' => $line->workshop_service_id,
                'code' => $line->service_code,
                'name' => $line->service_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
            ])->values(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse|RedirectResponse
    {
        abort_unless($transaction->isCompleted(), 404);

        $validated = $this->validateTransactionUpdatePayload($request);

        try {
            $updated = $this->transactionService->update($transaction, $validated, (int) auth()->id());
        } catch (InvalidArgumentException $e) {
            return $this->modalError($e->getMessage());
        }

        if ($request->expectsJson()) {
            return $this->modalSuccess(
                'Transaksi berhasil diperbarui.',
                ['transaction_no' => $updated->transaction_no, 'id' => $updated->id]
            );
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaksi '.$updated->transaction_no.' berhasil diperbarui.');
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse|RedirectResponse
    {
        try {
            $cancelled = $this->transactionService->cancel($transaction, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->modalError($e->getMessage());
        }

        $message = $cancelled->isDownPayment()
            ? 'Uang muka dikembalikan dan dicatat sebagai pengeluaran.'
            : 'Transaksi berhasil dibatalkan.';

        if ($request->expectsJson()) {
            return $this->modalSuccess(
                $message,
                ['transaction_no' => $cancelled->transaction_no, 'id' => $cancelled->id]
            );
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', $message);
    }

    private function validateTransactionPayload(Request $request, bool $requirePayment): array
    {
        $rules = [
            'customer_mode' => ['required', 'in:existing,umum,new'],
            'customer_id' => ['nullable', 'required_if:customer_mode,existing', 'exists:customers,id'],
            'new_customer' => ['nullable', 'required_if:customer_mode,new', 'array'],
            'new_customer.name' => ['required_if:customer_mode,new', 'string', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:30'],
            'new_customer.address' => ['nullable', 'string', 'max:500'],
            'technician_id' => ['nullable', 'required_with:services', 'exists:technicians,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'service_ticket_id' => ['nullable', 'uuid', 'exists:service_tickets,id'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.workshop_service_id' => ['required_with:services', 'exists:workshop_services,id'],
            'services.*.quantity' => ['required_with:services', 'integer', 'min:1'],
            'services.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($requirePayment) {
            $rules['payment_method'] = ['required', 'in:cash,qris,transfer'];
            $rules['bank_account_id'] = ['nullable', 'required_if:payment_method,transfer', 'exists:bank_accounts,id'];
            $rules['amount_paid'] = ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'];
        }

        return $this->applyTransactionWarranty($request->validate(array_merge(
            $rules,
            $this->transactionWarrantyRules($request)
        )));
    }

    private function validateTransactionUpdatePayload(Request $request): array
    {
        $rules = [
            'technician_id' => ['nullable', 'required_with:services', 'exists:technicians,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'exists:items,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.workshop_service_id' => ['required_with:services', 'exists:workshop_services,id'],
            'services.*.quantity' => ['required_with:services', 'integer', 'min:1'],
            'services.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,qris,transfer'],
            'bank_account_id' => ['nullable', 'required_if:payment_method,transfer', 'exists:bank_accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
        ];

        return $this->applyTransactionWarranty($request->validate(array_merge(
            $rules,
            $this->transactionWarrantyRules($request)
        )));
    }

    private function paymentBadge(Transaction $t): string
    {
        $label = \App\Support\PaymentMethodResolver::label($t->payment_method);

        if ($t->payment_method === 'transfer' && $t->bankAccount) {
            return '<span class="badge bg-info-subtle text-info" title="'.e($t->bankAccount->displayLabel()).'">'.e($label).'</span>';
        }

        return '<span class="badge bg-secondary-subtle text-secondary">'.e($label).'</span>';
    }

    private function resolveBillingTicket(Request $request): ?ServiceTicket
    {
        $ticketId = $request->string('ticket')->toString();
        if ($ticketId === '' || ! Str::isUuid($ticketId)) {
            return null;
        }

        return ServiceTicket::query()
            ->with(['customer', 'technician', 'paidTransaction', 'itemLines', 'serviceLines'])
            ->find($ticketId);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionWarrantyRules(Request $request): array
    {
        $hasTicket = $request->filled('service_ticket_id')
            || $request->route('transaction')?->service_ticket_id;

        if (! $hasTicket) {
            return [];
        }

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
    private function applyTransactionWarranty(array $validated): array
    {
        if (! array_key_exists('warranty_preset', $validated) && ! array_key_exists('warranty_days', $validated)) {
            return $validated;
        }

        $preset = $validated['warranty_preset'] ?? (string) config('service-tickets.warranty_default_days', 30);
        $validated['warranty_days'] = $preset === 'custom'
            ? (int) ($validated['warranty_days'] ?? 0)
            : (int) $preset;
        $validated['warranty_notes'] = filled($validated['warranty_notes'] ?? null)
            ? trim((string) $validated['warranty_notes'])
            : null;
        unset($validated['warranty_preset']);

        return $validated;
    }
}
