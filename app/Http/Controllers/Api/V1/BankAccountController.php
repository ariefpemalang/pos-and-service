<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\PaginatesApiQuery;
use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use PaginatesApiQuery;
    use RespondsWithApi;

    public function __construct()
    {
        $this->middleware('permission:bank account view')->only(['index', 'show']);
        $this->middleware('permission:bank account create')->only('store');
        $this->middleware('permission:bank account edit')->only('update');
        $this->middleware('permission:bank account delete')->only('destroy');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            $request,
            BankAccount::query(),
            ['bank_name', 'account_name', 'account_number'],
            ['id', 'bank_name', 'account_name', 'account_number', 'is_active', 'created_at', 'updated_at'],
        );

        return $this->apiSuccess('Daftar akun bank berhasil diambil.', [
            'items' => BankAccountResource::collection($paginator->items())->resolve(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $account = BankAccount::create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->apiSuccess('Akun bank berhasil ditambahkan.', [
            'bank_account' => new BankAccountResource($account),
        ], 201);
    }

    public function show(BankAccount $bankAccount): JsonResponse
    {
        return $this->apiSuccess('Detail akun bank berhasil diambil.', [
            'bank_account' => new BankAccountResource($bankAccount),
        ]);
    }

    public function update(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bankAccount->update([
            ...$validated,
            'is_active' => array_key_exists('is_active', $validated)
                ? $request->boolean('is_active')
                : $bankAccount->is_active,
        ]);

        return $this->apiSuccess('Akun bank berhasil diperbarui.', [
            'bank_account' => new BankAccountResource($bankAccount->fresh()),
        ]);
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        if ($bankAccount->transactions()->exists() || $bankAccount->purchases()->exists()) {
            return $this->apiError('Akun bank sudah dipakai di transaksi dan tidak dapat dihapus.', 422);
        }

        $bankAccount->delete();

        return $this->apiSuccess('Akun bank berhasil dihapus.');
    }
}
