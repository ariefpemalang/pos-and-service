<?php

namespace App\Http\Resources\Api;

use App\Support\PaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customer = $this->relationLoaded('customer') ? $this->customer : null;
        $technician = $this->relationLoaded('technician') ? $this->technician : null;
        $user = $this->relationLoaded('user') ? $this->user : null;
        $bank = $this->relationLoaded('bankAccount') ? $this->bankAccount : null;
        $ticket = $this->relationLoaded('serviceTicket') ? $this->serviceTicket : null;
        $cancelledBy = $this->relationLoaded('cancelledByUser') ? $this->cancelledByUser : null;
        $itemsLoaded = $this->relationLoaded('items');
        $servicesLoaded = $this->relationLoaded('serviceLines');

        $typeKey = $this->isDownPayment()
            ? ($this->isRefunded() ? 'down_payment_refunded' : 'down_payment')
            : $this->type;

        return [
            'id' => $this->id,
            'transaction_no' => $this->transaction_no,
            'type' => $this->type,
            'type_key' => $typeKey,
            'type_label' => $this->typeLabel($typeKey),
            'status' => $this->status,
            'status_label' => $this->status === 'cancelled' ? 'Batal' : 'Selesai',
            'is_completed' => $this->isCompleted(),
            'is_down_payment' => $this->isDownPayment(),
            'is_refunded' => $this->isRefunded(),
            'can_edit' => $this->isCompleted() && ! $this->isDownPayment(),
            'can_cancel' => $this->isCompleted() && ! ($this->isDownPayment() && $this->isRefunded()),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->displayCustomerName(),
            'customer' => $customer ? [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ] : null,
            'technician_id' => $this->technician_id,
            'technician' => $technician ? [
                'id' => $technician->id,
                'name' => $technician->name,
                'commission_percent' => (float) $technician->commission_percent,
                'photo' => $technician->photo,
                'photo_url' => $technician->absolutePhotoUrl(),
            ] : null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
            ] : null,
            'service_ticket_id' => $this->service_ticket_id,
            'service_ticket' => $ticket ? [
                'id' => $ticket->id,
                'ticket_no' => $ticket->ticket_no,
                'device_summary' => $ticket->deviceSummary(),
            ] : null,
            'subtotal_items' => (float) $this->subtotal_items,
            'subtotal_services' => (float) $this->subtotal_services,
            'discount' => (float) $this->discount,
            'down_payment' => $this->downPaymentAmount(),
            'job_total' => $this->jobTotal(),
            'total' => (float) $this->total,
            'technician_commission' => (float) $this->technician_commission,
            'owner_service_share' => (float) $this->owner_service_share,
            'owner_items_share' => (float) $this->owner_items_share,
            'owner_total_share' => (float) $this->owner_total_share,
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
            'cash_received' => $this->cash_received !== null ? (float) $this->cash_received : null,
            'cash_change' => $this->cash_change !== null ? (float) $this->cash_change : null,
            'items' => $this->when($itemsLoaded, fn () => $this->items->map(fn ($line) => [
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
            ])->values()),
            'services' => $this->when($servicesLoaded, fn () => $this->serviceLines->map(fn ($line) => [
                'workshop_service_id' => $line->workshop_service_id,
                'service_code' => $line->service_code,
                'service_name' => $line->service_name,
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'subtotal' => (float) $line->subtotal,
            ])->values()),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $cancelledBy ? [
                'id' => $cancelledBy->id,
                'name' => $cancelledBy->name,
            ] : null,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function typeLabel(string $key): string
    {
        return match ($key) {
            'sale' => 'Penjualan',
            'service' => 'Servis',
            'combined' => 'Gabungan',
            'down_payment' => 'Uang muka',
            'down_payment_refunded' => 'UMK dikembalikan',
            default => $key,
        };
    }
}
