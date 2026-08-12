<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ItemUnitResource;
use App\Models\ItemUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemUnitController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:item unit view')->only(['index', 'show']);
        $this->middleware('permission:item unit create')->only('store');
        $this->middleware('permission:item unit edit')->only('update');
        $this->middleware('permission:item unit delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            ItemUnit::query()
                ->select(['id', 'name', 'abbreviation', 'created_at', 'updated_at'])
                ->withCount('items'),
            ['name', 'abbreviation'],
            [],
        );

        return $this->apiSuccess('Daftar satuan barang berhasil diambil.', [
            'items' => ItemUnitResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:item_units,name'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
        ]);

        $unit = ItemUnit::create($validated);
        $unit->loadCount('items');

        return $this->apiSuccess('Satuan barang berhasil ditambahkan.', [
            'item_unit' => new ItemUnitResource($unit),
        ], 201);
    }

    public function show(ItemUnit $itemUnit): JsonResponse
    {
        $itemUnit->loadCount('items');

        return $this->apiSuccess('Detail satuan barang berhasil diambil.', [
            'item_unit' => new ItemUnitResource($itemUnit),
        ]);
    }

    public function update(Request $request, ItemUnit $itemUnit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:item_units,name,'.$itemUnit->id],
            'abbreviation' => ['nullable', 'string', 'max:20'],
        ]);

        $itemUnit->update($validated);

        return $this->apiSuccess('Satuan barang berhasil diperbarui.', [
            'item_unit' => new ItemUnitResource($itemUnit->fresh()->loadCount('items')),
        ]);
    }

    public function destroy(ItemUnit $itemUnit): JsonResponse
    {
        if ($itemUnit->items()->exists()) {
            return $this->apiError('Satuan masih digunakan oleh barang.', 422);
        }

        $itemUnit->delete();

        return $this->apiSuccess('Satuan barang berhasil dihapus.');
    }
}
