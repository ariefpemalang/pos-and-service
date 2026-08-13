<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use App\Support\StockReferenceValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

trait ManagesStockBatches
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    abstract protected function stockType(): string;

    abstract protected function stockService(): StockService;

    public function index(Request $request): JsonResponse
    {
        $type = $this->stockType();
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);
        $page = max((int) $request->integer('page', 1), 1);
        $search = trim((string) $request->query('search', ''));

        $base = StockMovement::query()
            ->where('type', $type)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('batch_no', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->select([
                DB::raw('COALESCE(batch_no, CONCAT("legacy-", id)) as batch_key'),
                'batch_no',
                DB::raw('MIN(id) as id'),
                DB::raw('COUNT(*) as item_count'),
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('MAX(reference_no) as reference_no'),
                DB::raw('MAX(notes) as notes'),
                DB::raw('MAX(user_id) as user_id'),
                DB::raw('MIN(created_at) as created_at'),
            ])
            ->groupBy(DB::raw('COALESCE(batch_no, CONCAT("legacy-", id))'), 'batch_no')
            ->orderByDesc(DB::raw('MIN(created_at)'));

        $countQuery = clone $base;
        $total = (int) DB::query()
            ->fromSub($countQuery->toBase(), 'stock_batches')
            ->count();

        $rows = $base->forPage($page, $perPage)->get();
        $userIds = $rows->pluck('user_id')->filter()->unique()->values();
        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $items = $rows->map(function ($row) use ($users, $type) {
            $preview = null;
            if ((int) $row->item_count === 1) {
                $movement = StockMovement::query()
                    ->with('item:id,code,name')
                    ->find($row->id);
                if ($movement?->item) {
                    $preview = $movement->item->code.' — '.$movement->item->name;
                }
            }

            return [
                'batch_key' => (string) $row->batch_key,
                'batch_no' => $row->batch_no,
                'item_count' => (int) $row->item_count,
                'total_quantity' => (int) $row->total_quantity,
                'reference_no' => $row->reference_no,
                'notes' => $row->notes,
                'items_label' => $preview
                    ?? ((int) $row->item_count.' barang'),
                'user' => $users->get($row->user_id)
                    ? [
                        'id' => $users->get($row->user_id)->id,
                        'name' => $users->get($row->user_id)->name,
                    ]
                    : null,
                'created_at' => $row->created_at
                    ? \Carbon\Carbon::parse($row->created_at)->toIso8601String()
                    : null,
                'type' => $type,
            ];
        })->values();

        $lastPage = max((int) ceil($total / $perPage), 1);

        return $this->apiSuccess(
            $type === 'in'
                ? 'Daftar stok masuk berhasil diambil.'
                : 'Daftar stok keluar berhasil diambil.',
            [
                'items' => $items,
                'meta' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'has_more' => $page < $lastPage,
                ],
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_no' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            StockReferenceValidator::assertManualReference($validated['reference_no'] ?? null);

            $result = $this->stockType() === 'in'
                ? $this->stockService()->stockInBatch(
                    $validated['items'],
                    (int) $request->user()->id,
                    $validated['reference_no'] ?? null,
                    $validated['notes'] ?? null,
                )
                : $this->stockService()->stockOutBatch(
                    $validated['items'],
                    (int) $request->user()->id,
                    $validated['reference_no'] ?? null,
                    $validated['notes'] ?? null,
                );
        } catch (InvalidArgumentException $e) {
            return $this->apiError($e->getMessage(), 422);
        }

        $count = $result['movements']->count();

        return $this->apiSuccess(
            $this->stockType() === 'in'
                ? "Stok masuk berhasil dicatat ({$count} barang)."
                : "Stok keluar berhasil dicatat ({$count} barang).",
            [
                'batch_no' => $result['batch_no'],
                'item_count' => $count,
            ],
            201
        );
    }

    public function show(string $batchNo): JsonResponse
    {
        $movements = StockMovement::query()
            ->where('type', $this->stockType())
            ->where('batch_no', $batchNo)
            ->with(['item.unit:id,name,abbreviation', 'user:id,name'])
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return $this->apiError('Batch stok tidak ditemukan.', 404);
        }

        $first = $movements->first();

        return $this->apiSuccess('Detail batch stok berhasil diambil.', [
            'batch' => [
                'batch_no' => $batchNo,
                'reference_no' => $first->reference_no,
                'notes' => $first->notes,
                'type' => $this->stockType(),
                'user' => $first->user
                    ? ['id' => $first->user->id, 'name' => $first->user->name]
                    : null,
                'created_at' => $first->created_at?->toIso8601String(),
                'items' => $movements->map(fn (StockMovement $m) => [
                    'item_id' => $m->item_id,
                    'code' => $m->item?->code,
                    'name' => $m->item?->name,
                    'photo_url' => $m->item?->absolutePhotoUrl(),
                    'quantity' => (int) $m->quantity,
                    'stock_before' => (int) $m->stock_before,
                    'stock_after' => (int) $m->stock_after,
                    'unit' => $m->item?->unit?->abbreviation ?: $m->item?->unit?->name,
                ])->values(),
            ],
        ]);
    }
}
