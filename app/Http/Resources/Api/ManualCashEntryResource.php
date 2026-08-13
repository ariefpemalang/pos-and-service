<?php

namespace App\Http\Resources\Api;

use App\Support\PaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ManualCashEntry */
class ManualCashEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bank = $this->relationLoaded('bankAccount') ? $this->bankAccount : null;
        $category = $this->relationLoaded('category') ? $this->category : null;
        $user = $this->relationLoaded('user') ? $this->user : null;
        $cancelledBy = $this->relationLoaded('cancelledByUser') ? $this->cancelledByUser : null;
        $paymentLabel = PaymentMethodResolver::label($this->payment_method);
        if ($this->payment_method === 'transfer' && $bank) {
            $paymentLabel .= ' — '.$bank->bank_name;
        }

        return [
            'id' => $this->id,
            'entry_no' => $this->entry_no,
            'type' => $this->type,
            'status' => $this->status,
            'status_label' => $this->isCompleted() ? 'Aktif' : 'Batal',
            'can_cancel' => $this->isCompleted(),
            'amount' => (float) $this->amount,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'payment_label' => $paymentLabel,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->when($this->relationLoaded('bankAccount'), fn () => $bank ? [
                'id' => $bank->id,
                'label' => $bank->displayLabel(),
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
            ] : null),
            'category_id' => $this->category_id,
            'category' => $this->when($this->relationLoaded('category'), fn () => $category ? [
                'id' => $category->id,
                'name' => $category->name,
            ] : null),
            'description' => $this->description,
            'user' => $this->when($this->relationLoaded('user'), fn () => $user ? [
                'id' => $user->id,
                'name' => $user->name,
            ] : null),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->when($this->relationLoaded('cancelledByUser'), fn () => $cancelledBy ? [
                'id' => $cancelledBy->id,
                'name' => $cancelledBy->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
