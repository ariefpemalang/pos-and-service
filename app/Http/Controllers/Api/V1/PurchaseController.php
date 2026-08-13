<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PurchaseResource;
use App\Models\BankAccount;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Support\PaymentMethodResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct(private PurchaseService $purchases)
    {
        $this->middleware('permission:purchase view')->only(['index', 'show', 'options']);
        $this->middleware('permission:purchase create')->only('store');
        $this->middleware('permission:purchase edit')->only('update');
        $this->middleware('permission:purchase delete')->only('destroy');
    }

    public function options(): JsonResponse
    {
        $methods = config('workshop.purchase_payment_methods', []);

        return $this->apiSuccess('Opsi pembelian berhasil diambil.', [
            'payment_methods' => collect($methods)
                ->map(fn ($label, $key) => ['key' => (string) $key, 'label' => (string) $label])
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

    public function index(Request $request): JsonResponse
    {
        $query = Purchase::query()->with([
            'supplier:id,code,name',
            'user:id,name',
            'bankAccount',
        ]);

        $status = trim((string) $request->query('status', ''));
        if (in_array($status, ['completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('purchase_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn (Builder $s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $this->paginateQuery($request, $query, [], []);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $base = Purchase::query()->where('status', 'completed');

        return $this->apiSuccess('Daftar pembelian berhasil diambil.', [
            'items' => PurchaseResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
            'summary' => [
                'today_count' => (clone $base)->whereDate('created_at', $today)->count(),
                'today_total' => (float) (clone $base)->whereDate('created_at', $today)->sum('total'),
                'month_count' => (clone $base)->whereDate('created_at', '>=', $monthStart)->count(),
                'month_total' => (float) (clone $base)->whereDate('created_at', '>=', $monthStart)->sum('total'),
                'completed_count' => Purchase::query()->where('status', 'completed')->count(),
                'cancelled_count' => Purchase::query()->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        try {
            $purchase = $this->purchases->create($validated, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage());
        }

        return $this->apiSuccess('Pembelian berhasil disimpan. Stok masuk tercatat.', [
            'purchase' => (new PurchaseResource($purchase))->resolve(),
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load([
            'supplier:id,code,name',
            'user:id,name',
            'bankAccount',
            'items',
            'cancelledByUser:id,name',
        ]);

        return $this->apiSuccess('Detail pembelian berhasil diambil.', [
            'purchase' => (new PurchaseResource($purchase))->resolve(),
        ]);
    }

    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        try {
            $updated = $this->purchases->update($purchase, $validated, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage());
        }

        return $this->apiSuccess('Pembelian berhasil diperbarui.', [
            'purchase' => (new PurchaseResource(
                $updated->load(['supplier:id,code,name', 'user:id,name', 'bankAccount', 'items'])
            ))->resolve(),
        ]);
    }

    public function destroy(Request $request, Purchase $purchase): JsonResponse
    {
        try {
            $cancelled = $this->purchases->cancel($purchase, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage());
        }

        return $this->apiSuccess('Pembelian dibatalkan. Stok dikembalikan.', [
            'purchase' => (new PurchaseResource(
                $cancelled->load([
                    'supplier:id,code,name',
                    'user:id,name',
                    'bankAccount',
                    'items',
                    'cancelledByUser:id,name',
                ])
            ))->resolve(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $forUpdate = false): array
    {
        $methods = PaymentMethodResolver::purchaseMethods();
        $rules = [
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:'.implode(',', $methods)],
            'bank_account_id' => ['nullable', 'required_if:payment_method,transfer', 'exists:bank_accounts,id'],
        ];

        if (! $forUpdate) {
            $rules = array_merge($rules, [
                'supplier_mode' => ['nullable', 'in:none,existing,new'],
                'supplier_id' => ['nullable', 'required_if:supplier_mode,existing', 'exists:suppliers,id'],
                'new_supplier' => ['nullable', 'array'],
                'new_supplier.name' => ['required_if:supplier_mode,new', 'string', 'max:255'],
                'new_supplier.phone' => ['nullable', 'string', 'max:30'],
                'new_supplier.email' => ['nullable', 'email', 'max:255'],
                'new_supplier.address' => ['nullable', 'string'],
            ]);
        }

        return $request->validate($rules);
    }
}
