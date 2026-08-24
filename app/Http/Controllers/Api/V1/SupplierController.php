<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Concerns\HandlesEditableEntityCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use HandlesEditableEntityCode;
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:supplier view')->only(['index', 'show']);
        $this->middleware('permission:supplier create')->only('store');
        $this->middleware('permission:supplier edit')->only('update');
        $this->middleware('permission:supplier delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            Supplier::query(),
            ['name', 'code', 'phone', 'email'],
            ['id', 'code', 'name', 'phone', 'email', 'address', 'notes', 'created_at', 'updated_at'],
        );

        return $this->apiSuccess('Daftar supplier berhasil diambil.', [
            'items' => SupplierResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ], $this->entityCodeRules('suppliers', 30)));

        $supplier = Supplier::create([
            ...collect($validated)->except(['code'])->all(),
            'code' => $this->resolveEntityCode($request, Supplier::class, $validated['code'] ?? null),
        ]);

        return $this->apiSuccess('Supplier berhasil ditambahkan.', [
            'supplier' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->apiSuccess('Detail supplier berhasil diambil.', [
            'supplier' => new SupplierResource($supplier),
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ], $this->entityCodeRules('suppliers', 30, $supplier->id, true)));

        $supplier->update([
            ...collect($validated)->except(['code'])->all(),
            'code' => trim($validated['code']),
        ]);

        return $this->apiSuccess('Supplier berhasil diperbarui.', [
            'supplier' => new SupplierResource($supplier->fresh()),
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return $this->apiSuccess('Supplier berhasil dihapus.');
    }
}
