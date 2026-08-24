<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Concerns\HandlesEditableEntityCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use HandlesEditableEntityCode;
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:customer view')->only(['index', 'show']);
        $this->middleware('permission:customer create')->only('store');
        $this->middleware('permission:customer edit')->only('update');
        $this->middleware('permission:customer delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            Customer::query(),
            ['name', 'code', 'phone', 'email'],
            ['id', 'code', 'name', 'phone', 'email', 'address', 'notes', 'created_at', 'updated_at'],
        );

        return $this->apiSuccess('Daftar pelanggan berhasil diambil.', [
            'items' => CustomerResource::collection($paginator->items())->resolve(),
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
        ], $this->entityCodeRules('customers', 30)));

        $customer = Customer::create([
            ...collect($validated)->except(['code'])->all(),
            'code' => $this->resolveEntityCode($request, Customer::class, $validated['code'] ?? null),
        ]);

        return $this->apiSuccess('Pelanggan berhasil ditambahkan.', [
            'customer' => new CustomerResource($customer),
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return $this->apiSuccess('Detail pelanggan berhasil diambil.', [
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ], $this->entityCodeRules('customers', 30, $customer->id, true)));

        $customer->update([
            ...collect($validated)->except(['code'])->all(),
            'code' => trim($validated['code']),
        ]);

        return $this->apiSuccess('Pelanggan berhasil diperbarui.', [
            'customer' => new CustomerResource($customer->fresh()),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->apiSuccess('Pelanggan berhasil dihapus.');
    }
}
