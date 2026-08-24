<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesEditableEntityCode;
use App\Http\Resources\Api\WorkshopServiceResource;
use App\Models\WorkshopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopServiceController extends Controller
{
    use HandlesEditableEntityCode;
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:workshop service view')->only(['index', 'show']);
        $this->middleware('permission:workshop service create')->only('store');
        $this->middleware('permission:workshop service edit')->only('update');
        $this->middleware('permission:workshop service delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $query = WorkshopService::query();
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $this->paginateQuery(
            $request,
            $query,
            ['name', 'code', 'description'],
            ['id', 'code', 'name', 'description', 'price', 'is_active', 'created_at', 'updated_at'],
        );

        return $this->apiSuccess('Daftar jasa servis berhasil diambil.', [
            'items' => WorkshopServiceResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->entityCodeRules('workshop_services', 50)));

        $service = WorkshopService::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'is_active' => $request->boolean('is_active', true),
            'code' => $this->resolveEntityCode($request, WorkshopService::class, $validated['code'] ?? null),
        ]);

        return $this->apiSuccess('Jasa servis berhasil ditambahkan.', [
            'workshop_service' => new WorkshopServiceResource($service),
        ], 201);
    }

    public function show(WorkshopService $workshopService): JsonResponse
    {
        return $this->apiSuccess('Detail jasa servis berhasil diambil.', [
            'workshop_service' => new WorkshopServiceResource($workshopService),
        ]);
    }

    public function update(Request $request, WorkshopService $workshopService): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->entityCodeRules('workshop_services', 50, $workshopService->id, true)));

        $workshopService->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'is_active' => array_key_exists('is_active', $validated)
                ? $request->boolean('is_active')
                : $workshopService->is_active,
            'code' => trim($validated['code']),
        ]);

        return $this->apiSuccess('Jasa servis berhasil diperbarui.', [
            'workshop_service' => new WorkshopServiceResource($workshopService->fresh()),
        ]);
    }

    public function destroy(WorkshopService $workshopService): JsonResponse
    {
        $workshopService->delete();

        return $this->apiSuccess('Jasa servis berhasil dihapus.');
    }
}
