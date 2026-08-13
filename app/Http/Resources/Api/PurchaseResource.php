<?php

namespace App\Http\Resources\Api;

use App\Support\PaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Purchase */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supplier = $this->relationLoaded('supplier') ? $this->supplier : null;
        $user = $this->relationLoaded('user') ? $this->user : null;
        $bank = $this->relationLoaded('bankAccount') ? $this->bankAccount : null;
        $cancelledBy = $this->relationLoaded('cancelledByUser') ? $this->cancelledByUser : null;
        $itemsLoaded = $this->relationLoaded('items');

        return [
            'id' => $this->id,
            'purchase_no' => $this->purchase_no,
            'status' => $this->status,
            'status_label' => $this->status === 'cancelled' ? 'Batal' : 'Selesai',
            'is_completed' => $this->isCompleted(),
            'can_edit' => $this->isCompleted(),
            'can_cancel' => $this->isCompleted(),
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->displaySupplierName(),
            'supplier' => $supplier ? [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
            ] : null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
            ] : null,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'payment_method' => $this->payment_method,
            'payment_label' => PaymentMethodResolver::label($this->payment_method),
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $bank ? [
                'id' => $bank->id,
                'label' => $bank->displayLabel(),
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
            ] : null,
            'items' => $this->when($itemsLoaded, fn () => $this->items->map(fn ($line) => [
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
            ])->values()),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $cancelledBy ? [
                'id' => $cancelledBy->id,
                'name' => $cancelledBy->name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
