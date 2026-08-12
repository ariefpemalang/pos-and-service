<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ItemCategoryResource;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCategoryController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:item category view')->only(['index', 'show']);
        $this->middleware('permission:item category create')->only('store');
        $this->middleware('permission:item category edit')->only('update');
        $this->middleware('permission:item category delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            ItemCategory::query()
                ->select(['id', 'name', 'description', 'created_at', 'updated_at'])
                ->withCount('items'),
            ['name', 'description'],
            [],
        );

        return $this->apiSuccess('Daftar kategori barang berhasil diambil.', [
            'items' => ItemCategoryResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:item_categories,name'],
            'description' => ['nullable', 'string'],
        ]);

        $category = ItemCategory::create($validated);
        $category->loadCount('items');

        return $this->apiSuccess('Kategori barang berhasil ditambahkan.', [
            'item_category' => new ItemCategoryResource($category),
        ], 201);
    }

    public function show(ItemCategory $itemCategory): JsonResponse
    {
        $itemCategory->loadCount('items');

        return $this->apiSuccess('Detail kategori barang berhasil diambil.', [
            'item_category' => new ItemCategoryResource($itemCategory),
        ]);
    }

    public function update(Request $request, ItemCategory $itemCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:item_categories,name,'.$itemCategory->id],
            'description' => ['nullable', 'string'],
        ]);

        $itemCategory->update($validated);
        $itemCategory->loadCount('items');

        return $this->apiSuccess('Kategori barang berhasil diperbarui.', [
            'item_category' => new ItemCategoryResource($itemCategory->fresh()->loadCount('items')),
        ]);
    }

    public function destroy(ItemCategory $itemCategory): JsonResponse
    {
        if ($itemCategory->items()->exists()) {
            return $this->apiError('Kategori masih digunakan oleh barang.', 422);
        }

        $itemCategory->delete();

        return $this->apiSuccess('Kategori barang berhasil dihapus.');
    }
}
