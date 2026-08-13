<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TransactionResource;
use App\Models\BankAccount;
use App\Models\Technician;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TransactionController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct(private TransactionService $transactions)
    {
        $this->middleware('permission:transaction view')->only(['index', 'show', 'options']);
        $this->middleware('permission:transaction create')->only('store');
        $this->middleware('permission:transaction edit')->only('update');
        $this->middleware('permission:transaction delete')->only('destroy');
    }

    public function options(): JsonResponse
    {
        $methods = config('workshop.payment_methods', []);
        $techPercent = (int) config('workshop.default_technician_commission_percent', 20);

        return $this->apiSuccess('Opsi transaksi berhasil diambil.', [
            'walk_in_label' => (string) config('workshop.walk_in_customer_label', 'Umum'),
            'tech_percent' => $techPercent,
            'owner_percent' => 100 - $techPercent,
            'payment_methods' => collect($methods)
                ->map(fn ($label, $key) => ['key' => (string) $key, 'label' => (string) $label])
                ->values(),
            'technicians' => Technician::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'commission_percent'])
                ->map(fn (Technician $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'name' => $t->name,
                    'commission_percent' => (float) $t->commission_percent,
                ])
                ->values(),
            'bank_accounts' => $this->activeBanks(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query()->with([
            'customer:id,code,name,phone',
            'technician:id,code,name,commission_percent',
            'user:id,name',
            'bankAccount',
            'serviceTicket:id,ticket_no,brand,model,device_type',
        ]);

        $status = trim((string) $request->query('status', ''));
        if (in_array($status, ['completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $type = trim((string) $request->query('type', ''));
        if ($type === 'down_payment') {
            $query->where('is_down_payment', true);
        } elseif (in_array($type, ['sale', 'service', 'combined'], true)) {
            $query->where('is_down_payment', false)->where('type', $type);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('transaction_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $this->paginateQuery($request, $query, [], []);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $base = Transaction::query()->where('status', 'completed');

        return $this->apiSuccess('Daftar transaksi berhasil diambil.', [
            'items' => TransactionResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
            'summary' => [
                'today_count' => (clone $base)->whereDate('created_at', $today)->count(),
                'today_total' => (float) (clone $base)->whereDate('created_at', $today)->sum('total'),
                'month_count' => (clone $base)->whereDate('created_at', '>=', $monthStart)->count(),
                'month_total' => (float) (clone $base)->whereDate('created_at', '>=', $monthStart)->sum('total'),
                'completed_count' => Transaction::query()->where('status', 'completed')->count(),
                'cancelled_count' => Transaction::query()->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStore($request);

        try {
            $transaction = $this->transactions->create($validated, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage());
        }

        return $this->apiSuccess('Transaksi berhasil disimpan.', [
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ], 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load([
            'customer:id,code,name,phone',
            'technician:id,code,name,commission_percent',
            'user:id,name',
            'bankAccount',
            'items',
            'serviceLines',
            'serviceTicket:id,ticket_no,brand,model,device_type',
            'cancelledByUser:id,name',
        ]);

        return $this->apiSuccess('Detail transaksi berhasil diambil.', [
            'transaction' => (new TransactionResource($transaction))->resolve(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $validated = $this->validateUpdate($request);

        try {
            $updated = $this->transactions->update($transaction, $validated, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage());
        }

        return $this->apiSuccess('Transaksi berhasil diperbarui.', [
            'transaction' => (new TransactionResource(
                $updated->load([
                    'customer:id,code,name,phone',
                    'technician:id,code,name,commission_percent',
                    'user:id,name',
                    'bankAccount',
                    'items',
                    'serviceLines',
                    'serviceTicket:id,ticket_no,brand,model,device_type',
                ])
            ))->resolve(),
        ]);
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            $cancelled = $this->transactions->cancel($transaction, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage());
        }

        $message = $cancelled->isDownPayment()
            ? 'Uang muka dikembalikan dan dicatat sebagai pengeluaran.'
            : 'Transaksi berhasil dibatalkan.';

        return $this->apiSuccess($message, [
            'transaction' => (new TransactionResource(
                $cancelled->load([
                    'customer:id,code,name,phone',
                    'technician:id,code,name,commission_percent',
                    'user:id,name',
                    'bankAccount',
                    'items',
                    'serviceLines',
                    'cancelledByUser:id,name',
                ])
            ))->resolve(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStore(Request $request): array
    {
        return $request->validate([
            'customer_mode' => ['required', 'in:existing,umum,new'],
            'customer_id' => ['nullable', 'required_if:customer_mode,existing', 'exists:customers,id'],
            'new_customer' => ['nullable', 'array'],
            'new_customer.name' => ['required_if:customer_mode,new', 'string', 'max:255'],
            'new_customer.phone' => ['nullable', 'string', 'max:30'],
            'new_customer.address' => ['nullable', 'string', 'max:500'],
            'technician_id' => ['nullable', 'required_with:services.0', 'exists:technicians,id'],
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
            'payment_method' => ['required', 'in:'.implode(',', array_keys(config('workshop.payment_methods', [])))],
            'bank_account_id' => ['nullable', 'required_if:payment_method,transfer', 'exists:bank_accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUpdate(Request $request): array
    {
        return $request->validate([
            'technician_id' => ['nullable', 'required_with:services.0', 'exists:technicians,id'],
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
            'payment_method' => ['required', 'in:'.implode(',', array_keys(config('workshop.payment_methods', [])))],
            'bank_account_id' => ['nullable', 'required_if:payment_method,transfer', 'exists:bank_accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'required_if:payment_method,cash'],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function activeBanks()
    {
        return BankAccount::query()
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
            ->values();
    }
}
