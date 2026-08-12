<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
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
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);
        $search = trim((string) $request->query('search', ''));

        $query = Customer::query()
            ->select([
                'id',
                'code',
                'name',
                'phone',
                'email',
                'address',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        return $this->apiSuccess('Daftar pelanggan berhasil diambil.', [
            'items' => CustomerResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::create([
            ...$validated,
            'code' => Customer::generateCode(),
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update($validated);

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
