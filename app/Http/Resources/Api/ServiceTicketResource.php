<?php

namespace App\Http\Resources\Api;

use App\Support\PaymentMethodResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ServiceTicket */
class ServiceTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paid = $this->relationLoaded('paidTransaction') ? $this->paidTransaction : null;
        $dpTx = $this->relationLoaded('downPaymentTransaction') ? $this->downPaymentTransaction : null;
        $bank = $this->relationLoaded('downPaymentBankAccount') ? $this->downPaymentBankAccount : null;
        $customer = $this->relationLoaded('customer') ? $this->customer : null;
        $technician = $this->relationLoaded('technician') ? $this->technician : null;

        return [
            'id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_color' => $this->statusColor(),
            'is_closed' => $this->isClosed(),
            'can_edit' => ! $this->isClosed(),
            'can_delete' => in_array($this->status, ['received', 'cancelled'], true) && ! $dpTx,
            'can_create_invoice' => $this->canCreateInvoice(),
            'device_type' => $this->device_type,
            'device_type_label' => $this->deviceTypeLabel(),
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'serial_imei' => $this->serial_imei,
            'device_summary' => $this->deviceSummary(),
            'specs' => $this->specs,
            'accessories' => $this->accessories,
            'complaint' => $this->complaint,
            'diagnosis' => $this->diagnosis,
            'internal_notes' => $this->internal_notes,
            'invoice_notes' => $this->invoiceNotes(),
            'estimated_cost' => $this->estimated_cost !== null ? (float) $this->estimated_cost : null,
            'down_payment' => $this->downPaymentAmount(),
            'down_payment_payment_method' => $this->down_payment_payment_method,
            'down_payment_payment_label' => PaymentMethodResolver::label($this->down_payment_payment_method),
            'down_payment_bank_account_id' => $this->down_payment_bank_account_id,
            'down_payment_bank_account' => $this->when($this->relationLoaded('downPaymentBankAccount'), fn () => $bank ? [
                'id' => $bank->id,
                'label' => $bank->displayLabel(),
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
            ] : null),
            'remaining_estimate' => $this->remainingEstimate(),
            'warranty_days' => $this->warrantyDays(),
            'warranty_notes' => $this->warranty_notes,
            'warranty_status' => $this->warrantyStatus(),
            'warranty_status_label' => $this->warrantyStatusLabel(),
            'warranty_label' => $this->warrantyIndexLabel(),
            'warranty_period_label' => $this->warrantyPeriodLabel(),
            'warranty_summary' => $this->warrantySummary(),
            'warranty_starts_at' => $this->warrantyStartsAt()?->toIso8601String(),
            'warranty_ends_at' => $this->warrantyEndsAt()?->toIso8601String(),
            'warranty_days_remaining' => $this->warrantyDaysRemaining(),
            'tracking_token' => $this->when(
                $this->relationLoaded('statusLogs') || $this->relationLoaded('customer'),
                $this->tracking_token,
            ),
            'tracking_url' => $this->when(
                $this->relationLoaded('statusLogs') || $this->relationLoaded('customer'),
                $this->trackingUrl(),
            ),
            'customer_id' => $this->customer_id,
            'technician_id' => $this->technician_id,
            'customer' => $this->when($this->relationLoaded('customer'), fn () => $customer ? [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
            ] : null),
            'technician' => $this->when($this->relationLoaded('technician'), fn () => $technician ? [
                'id' => $technician->id,
                'name' => $technician->name,
                'commission_percent' => $technician->commission_percent !== null
                    ? (float) $technician->commission_percent
                    : null,
                'photo' => $technician->photo,
                'photo_url' => $technician->absolutePhotoUrl(),
            ] : null),
            'paid_transaction' => $this->when($this->relationLoaded('paidTransaction'), fn () => $paid ? [
                'id' => $paid->id,
                'transaction_no' => $paid->transaction_no,
                'total' => (float) $paid->total,
            ] : null),
            'down_payment_transaction' => $this->when($this->relationLoaded('downPaymentTransaction'), fn () => $dpTx ? [
                'id' => $dpTx->id,
                'transaction_no' => $dpTx->transaction_no,
                'total' => (float) $dpTx->total,
                'payment_method' => $dpTx->payment_method,
            ] : null),
            'status_logs' => $this->when(
                $this->relationLoaded('statusLogs'),
                fn () => $this->statusLogs->map(fn ($log) => [
                    'id' => $log->id,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'to_status_label' => $log->toStatusLabel(),
                    'note' => $log->note,
                    'notified' => (bool) $log->notified,
                    'notify_error' => $log->notify_error,
                    'user_name' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->values(),
            ),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
