<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Technician;
use App\Models\WorkshopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityCodeController extends Controller
{
    use RespondsWithApi;

    /** @var array<string, array{class-string, string}> */
    private const TYPES = [
        'customer' => [Customer::class, 'customer create'],
        'supplier' => [Supplier::class, 'supplier create'],
        'item' => [Item::class, 'item create'],
        'technician' => [Technician::class, 'technician create'],
        'workshop_service' => [WorkshopService::class, 'workshop service create'],
    ];

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:customer,supplier,item,technician,workshop_service'],
        ]);

        [$modelClass, $permission] = self::TYPES[$validated['type']];
        abort_unless($request->user()?->can($permission), 403);

        return $this->apiSuccess('Kode saran berhasil diambil.', [
            'code' => $modelClass::generateCode(),
        ]);
    }
}
