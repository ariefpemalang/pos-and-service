<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Concerns\HandlesEditableEntityCode;
use App\Http\Controllers\Concerns\HandlesEntityPhoto;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TechnicianResource;
use App\Models\Technician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianController extends Controller
{
    use HandlesEditableEntityCode, HandlesEntityPhoto;
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:technician view')->only(['index', 'show']);
        $this->middleware('permission:technician create')->only('store');
        $this->middleware('permission:technician edit')->only('update');
        $this->middleware('permission:technician delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            Technician::query(),
            ['name', 'code', 'phone', 'email', 'specialty'],
            [
                'id',
                'code',
                'name',
                'photo',
                'phone',
                'email',
                'specialty',
                'commission_percent',
                'is_active',
                'notes',
                'created_at',
                'updated_at',
            ],
        );

        return $this->apiSuccess('Daftar teknisi berhasil diambil.', [
            'items' => TechnicianResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ], $this->entityCodeRules('technicians', 30), $this->photoRules()));

        $technician = Technician::create([
            ...collect($validated)->except(['photo', 'remove_photo', 'code'])->all(),
            'code' => $this->resolveEntityCode($request, Technician::class, $validated['code'] ?? null),
            'photo' => $this->resolvePhotoPath($request, 'photos/technicians'),
            'commission_percent' => $validated['commission_percent']
                ?? config('workshop.default_technician_commission_percent', 20),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->apiSuccess('Teknisi berhasil ditambahkan.', [
            'technician' => new TechnicianResource($technician),
        ], 201);
    }

    public function show(Technician $technician): JsonResponse
    {
        return $this->apiSuccess('Detail teknisi berhasil diambil.', [
            'technician' => new TechnicianResource($technician),
        ]);
    }

    public function update(Request $request, Technician $technician): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ], $this->entityCodeRules('technicians', 30, $technician->id, true), $this->photoRules()));

        $technician->update([
            ...collect($validated)->except(['photo', 'remove_photo', 'code'])->all(),
            'code' => trim($validated['code']),
            'photo' => $this->resolvePhotoPath($request, 'photos/technicians', $technician->photo),
            'commission_percent' => $validated['commission_percent']
                ?? config('workshop.default_technician_commission_percent', 20),
            'is_active' => array_key_exists('is_active', $validated)
                ? $request->boolean('is_active')
                : $technician->is_active,
        ]);

        return $this->apiSuccess('Teknisi berhasil diperbarui.', [
            'technician' => new TechnicianResource($technician->fresh()),
        ]);
    }

    public function destroy(Technician $technician): JsonResponse
    {
        $technician->deletePhotoFile();
        $technician->delete();

        return $this->apiSuccess('Teknisi berhasil dihapus.');
    }
}
