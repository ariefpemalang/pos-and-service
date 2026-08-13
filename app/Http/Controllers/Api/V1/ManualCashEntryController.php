<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ManualCashEntryResource;
use App\Models\BankAccount;
use App\Models\FinancialCategory;
use App\Models\ManualCashEntry;
use App\Services\ManualCashEntryService;
use App\Support\PaymentMethodResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ManualCashEntryController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct(private ManualCashEntryService $service)
    {
        $this->middleware('permission:manual income view')->only(['incomeIndex', 'incomeShow', 'incomeOptions']);
        $this->middleware('permission:manual income create')->only('incomeStore');
        $this->middleware('permission:manual income cancel')->only('incomeDestroy');
        $this->middleware('permission:manual expense view')->only(['expenseIndex', 'expenseShow', 'expenseOptions']);
        $this->middleware('permission:manual expense create')->only('expenseStore');
        $this->middleware('permission:manual expense cancel')->only('expenseDestroy');
    }

    public function incomeOptions(): JsonResponse
    {
        return $this->options('income');
    }

    public function expenseOptions(): JsonResponse
    {
        return $this->options('expense');
    }

    public function incomeIndex(Request $request): JsonResponse
    {
        return $this->index($request, 'income');
    }

    public function expenseIndex(Request $request): JsonResponse
    {
        return $this->index($request, 'expense');
    }

    public function incomeStore(Request $request): JsonResponse
    {
        return $this->store($request, 'income');
    }

    public function expenseStore(Request $request): JsonResponse
    {
        return $this->store($request, 'expense');
    }

    public function incomeShow(ManualCashEntry $manualCashEntry): JsonResponse
    {
        return $this->show($manualCashEntry, 'income');
    }

    public function expenseShow(ManualCashEntry $manualCashEntry): JsonResponse
    {
        return $this->show($manualCashEntry, 'expense');
    }

    public function incomeDestroy(Request $request, ManualCashEntry $manualCashEntry): JsonResponse
    {
        return $this->destroy($request, $manualCashEntry, 'income');
    }

    public function expenseDestroy(Request $request, ManualCashEntry $manualCashEntry): JsonResponse
    {
        return $this->destroy($request, $manualCashEntry, 'expense');
    }

    private function options(string $type): JsonResponse
    {
        $methods = $type === 'expense'
            ? config('workshop.purchase_payment_methods', [])
            : config('workshop.payment_methods', []);

        $categories = FinancialCategory::query()
            ->where('type', $type)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->apiSuccess('Opsi entri kas berhasil diambil.', [
            'type' => $type,
            'categories' => $categories->map(fn (FinancialCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values(),
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

    private function index(Request $request, string $type): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->integer('category_id');
        $status = $request->string('status')->toString();

        $query = ManualCashEntry::query()
            ->with(['category:id,name', 'user:id,name', 'bankAccount', 'cancelledByUser:id,name'])
            ->where('type', $type)
            ->whereBetween('occurred_at', [$from, $to])
            ->when($categoryId > 0, fn (Builder $q) => $q->where('category_id', $categoryId))
            ->when($status === 'completed' || $status === 'cancelled', fn (Builder $q) => $q->where('status', $status))
            ->latest('occurred_at')
            ->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('entry_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->paginate($perPage);

        $summaryBase = ManualCashEntry::query()
            ->where('type', $type)
            ->whereBetween('occurred_at', [$from, $to]);

        return $this->apiSuccess(
            $type === 'income' ? 'Daftar pemasukan manual berhasil diambil.' : 'Daftar pengeluaran manual berhasil diambil.',
            [
                'items' => ManualCashEntryResource::collection($paginator->items())->resolve(),
                'meta' => $this->paginationMeta($paginator),
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'summary' => [
                    'count' => (int) (clone $summaryBase)->count(),
                    'completed_count' => (int) (clone $summaryBase)->completed()->count(),
                    'completed_amount' => (float) (clone $summaryBase)->completed()->sum('amount'),
                    'cancelled_count' => (int) (clone $summaryBase)->where('status', 'cancelled')->count(),
                ],
            ],
        );
    }

    private function store(Request $request, string $type): JsonResponse
    {
        $methods = $type === 'expense'
            ? PaymentMethodResolver::purchaseMethods()
            : array_keys(config('workshop.payment_methods', []));

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:financial_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_at' => ['required', 'date'],
            'payment_method' => ['required', 'in:'.implode(',', $methods)],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $entry = $this->service->create($validated, $type, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        $entry->load(['category', 'user', 'bankAccount', 'cancelledByUser']);

        return $this->apiSuccess(
            $type === 'income' ? 'Pemasukan manual berhasil dicatat.' : 'Pengeluaran manual berhasil dicatat.',
            ['entry' => new ManualCashEntryResource($entry)],
            201,
        );
    }

    private function show(ManualCashEntry $entry, string $type): JsonResponse
    {
        abort_unless($entry->type === $type, 404);
        $entry->load(['category', 'user', 'bankAccount', 'cancelledByUser']);

        return $this->apiSuccess('Detail entri kas berhasil diambil.', [
            'entry' => new ManualCashEntryResource($entry),
        ]);
    }

    private function destroy(Request $request, ManualCashEntry $entry, string $type): JsonResponse
    {
        abort_unless($entry->type === $type, 404);

        try {
            $cancelled = $this->service->cancel($entry, (int) $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        return $this->apiSuccess(
            $type === 'income' ? 'Pemasukan manual berhasil dibatalkan.' : 'Pengeluaran manual berhasil dibatalkan.',
            ['entry' => new ManualCashEntryResource($cancelled)],
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth()->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
